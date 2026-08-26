<?php
// This file is part of Moodle - http://moodle.org/.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_casospracticos\question_manager;
use local_casospracticos\feedback_review_snapshot;

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'file' => null,
    'apply' => false,
    'allow-verified' => false,
    'backup-dir' => null,
], [
    'h' => 'help',
    'f' => 'file',
]);

if ($options['help'] || empty($options['file'])) {
    $help = <<<TXT
Validate or apply consolidated feedback reviews.

Dry-run (default):
  php cli/apply_feedback_reviews.php --file=/absolute/reviews.json

Apply after editorial consolidation:
  php cli/apply_feedback_reviews.php --file=/absolute/reviews.json --apply \\
      --backup-dir=/absolute/backup/directory

Options:
  --allow-verified  Permit proposals carrying editorial_status=verified.
                    Without it, those proposals are stored as needs_review.
TXT;
    cli_writeln($help);
    exit($options['help'] ? 0 : 1);
}

if ($unrecognized) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}

$inputfile = (string) $options['file'];
if (!is_readable($inputfile)) {
    cli_error('Cannot read input file: ' . $inputfile);
}

$payload = json_decode(file_get_contents($inputfile));
if (!is_array($payload)) {
    cli_error('Input must be a JSON array of review objects.');
}

$accepted = [];
$errors = [];
$seen = [];
foreach ($payload as $offset => $review) {
    $label = 'item ' . ($offset + 1);
    if (!is_object($review) || empty($review->questionid)) {
        $errors[] = "$label: missing questionid";
        continue;
    }
    if (($review->contenttype ?? '') !== 'local_cp_question') {
        $errors[] = "$label: contenttype must be local_cp_question";
        continue;
    }
    $questionid = (int) $review->questionid;
    if (isset($seen[$questionid])) {
        $errors[] = "$label: duplicate questionid $questionid";
        continue;
    }
    $seen[$questionid] = true;

    $question = question_manager::get_with_answers($questionid);
    if (!$question) {
        $errors[] = "$label: question $questionid does not exist";
        continue;
    }
    $decision = (string) ($review->decision ?? '');
    if (!in_array($decision, ['approved', 'blocked', 'no_change'], true)) {
        $errors[] = "$label: invalid decision";
        continue;
    }

    $source = feedback_review_snapshot::build($questionid);
    $actualhash = feedback_review_snapshot::hash($source);
    if (!hash_equals($actualhash, (string) ($review->source_sha256 ?? ''))) {
        $errors[] = "$label: source hash mismatch for question $questionid";
        continue;
    }

    if ($decision === 'approved') {
        if (trim((string) ($review->reasoning ?? '')) === ''
                || trim((string) ($review->modelanswer ?? '')) === '') {
            $errors[] = "$label: an approved review needs reasoning and modelanswer";
            continue;
        }
        $knownanswerids = array_map('intval', array_keys($question->answers));
        $providedanswerids = [];
        foreach (($review->answer_feedback ?? []) as $answerfeedback) {
            $answerid = (int) ($answerfeedback->answerid ?? 0);
            if (!in_array($answerid, $knownanswerids, true)) {
                $errors[] = "$label: answer $answerid does not belong to question $questionid";
                continue 2;
            }
            if (isset($providedanswerids[$answerid]) || trim((string) ($answerfeedback->feedback ?? '')) === '') {
                $errors[] = "$label: duplicated or empty feedback for answer $answerid";
                continue 2;
            }
            $providedanswerids[$answerid] = true;
        }
        if (in_array($question->qtype, ['multichoice', 'truefalse', 'matching'], true)
                && count($providedanswerids) !== count($knownanswerids)) {
            $errors[] = "$label: feedback must cover every answer for question $questionid";
            continue;
        }
        $requestedstatus = (string) ($review->editorial_status ?? 'needs_review');
        if (!in_array($requestedstatus, ['needs_review', 'verified'], true)) {
            $errors[] = "$label: approved review has incompatible editorial_status";
            continue;
        }
        if ($requestedstatus === 'verified' && $options['allow-verified']) {
            if (!empty($review->alerts) || !is_array($review->normative_checks ?? null)) {
                $errors[] = "$label: verified review has alerts or lacks normative_checks";
                continue;
            }
            $requiredchecks = max(1, count($source['normativa']));
            if (count($review->normative_checks) < $requiredchecks) {
                $errors[] = "$label: insufficient normative checks for verified status";
                continue;
            }
            foreach ($review->normative_checks as $check) {
                if (($check->status ?? '') !== 'verified' || empty($check->official_url)) {
                    $errors[] = "$label: every normative check needs verified status and an official URL";
                    continue 2;
                }
            }
        }
    } else if ($decision === 'blocked' && empty($review->alerts)) {
        $errors[] = "$label: blocked review must explain the conflict in alerts";
        continue;
    }
    $accepted[] = [$question, $review, $source, $actualhash];
}

foreach ($errors as $error) {
    cli_writeln('ERROR ' . $error, STDERR);
}
cli_writeln('Validated: ' . count($accepted) . '; rejected: ' . count($errors));
if ($errors || !$options['apply']) {
    exit($errors ? 2 : 0);
}

$backupdir = rtrim((string) $options['backup-dir'], DIRECTORY_SEPARATOR);
if ($backupdir === '' || !is_dir($backupdir) || !is_writable($backupdir)) {
    cli_error('--backup-dir must be an existing writable directory when --apply is used.');
}

$backup = [
    'created_at' => gmdate('c'),
    'input_file' => realpath($inputfile),
    'questions' => array_map(static function(array $item): array {
        return ['source_sha256' => $item[3], 'source' => $item[2]];
    }, $accepted),
];
$backuppath = $backupdir . DIRECTORY_SEPARATOR . 'cp-feedback-' . gmdate('Ymd-His')
    . '-' . bin2hex(random_bytes(4)) . '.json';
if (file_put_contents($backuppath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
    cli_error('Could not write backup: ' . $backuppath);
}

$transaction = $DB->start_delegated_transaction();
try {
    foreach ($accepted as [$question, $review]) {
        $currenthash = feedback_review_snapshot::hash(feedback_review_snapshot::build((int) $question->id));
        if (!hash_equals((string) $review->source_sha256, $currenthash)) {
            throw new moodle_exception('error:reviewsourcestale', 'local_casospracticos', '', $question->id);
        }
        if ($review->decision === 'no_change') {
            continue;
        }
        $status = $review->decision === 'blocked' ? 'blocked' : (string) ($review->editorial_status ?? 'needs_review');
        if ($status === 'verified' && !$options['allow-verified']) {
            $status = 'needs_review';
        }
        if (!in_array($status, ['needs_review', 'verified', 'blocked'], true)) {
            throw new coding_exception('Invalid editorial status for question ' . $question->id);
        }
        $update = (object) [
            'id' => (int) $question->id,
            'feedbackstatus' => $status,
            'feedbackverifiedat' => $status === 'verified' ? time() : null,
        ];
        if ($review->decision === 'approved') {
            $update->reasoning = (string) $review->reasoning;
            $update->reasoningformat = FORMAT_HTML;
            $update->modelanswer = (string) $review->modelanswer;
            $update->modelanswerformat = FORMAT_HTML;
        }
        question_manager::update($update);
        if ($review->decision === 'approved') {
            foreach (($review->answer_feedback ?? []) as $answerfeedback) {
                $answerupdate = (object) [
                    'id' => (int) $answerfeedback->answerid,
                    'feedback' => (string) $answerfeedback->feedback,
                    'feedbackformat' => FORMAT_HTML,
                ];
                $DB->update_record('local_cp_answers', $answerupdate);
            }
        }
    }
    $transaction->allow_commit();
} catch (Throwable $e) {
    $transaction->rollback($e);
    throw $e;
}

cli_writeln('Applied: ' . count($accepted) . '; backup: ' . $backuppath);
