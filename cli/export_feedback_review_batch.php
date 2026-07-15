<?php
// This file is part of Moodle - http://moodle.org/.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../lib.php');

use local_casospracticos\question_manager;
use local_casospracticos\feedback_review_snapshot;

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'ids' => null,
    'ids-file' => null,
    'output' => null,
], [
    'h' => 'help',
    'o' => 'output',
]);

if ($options['help'] || empty($options['output']) || (empty($options['ids']) && empty($options['ids-file']))) {
    $help = <<<TXT
Export self-contained packets for legal and pedagogical feedback review.

  php cli/export_feedback_review_batch.php --ids=10,20,30 --output=/tmp/batch.json
  php cli/export_feedback_review_batch.php --ids-file=/tmp/ids.txt --output=/tmp/batch.json

The IDs file may contain a JSON array or IDs separated by whitespace/commas.
TXT;
    cli_writeln($help);
    exit($options['help'] ? 0 : 1);
}
if ($unrecognized) {
    cli_error('Unknown options: ' . implode(', ', $unrecognized));
}

$rawids = (string) ($options['ids'] ?? '');
if (!empty($options['ids-file'])) {
    if (!is_readable($options['ids-file'])) {
        cli_error('Cannot read IDs file: ' . $options['ids-file']);
    }
    $rawids .= ',' . file_get_contents($options['ids-file']);
}
$decodedids = json_decode(trim($rawids), true);
if (is_array($decodedids)) {
    $ids = array_map('intval', $decodedids);
} else {
    $ids = array_map('intval', preg_split('/[\s,]+/', trim($rawids), -1, PREG_SPLIT_NO_EMPTY));
}
$ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
if (!$ids) {
    cli_error('No valid question IDs supplied.');
}

$normativabatch = local_casospracticos_get_normativa_links($ids);
$packets = [];
foreach ($ids as $questionid) {
    $question = question_manager::get_with_answers($questionid);
    if (!$question) {
        cli_writeln("Skipping missing question $questionid", STDERR);
        continue;
    }
    $case = $DB->get_record('local_cp_cases', ['id' => $question->caseid], 'id,name,statement,statementformat');
    if (!$case) {
        cli_writeln("Skipping question $questionid with missing case", STDERR);
        continue;
    }

    $answers = array_map(static function($answer): array {
        return [
            'id' => (int) $answer->id,
            'answer' => (string) $answer->answer,
            'fraction' => (float) $answer->fraction,
            'feedback' => (string) ($answer->feedback ?? ''),
        ];
    }, array_values($question->answers));
    $snapshot = feedback_review_snapshot::build($questionid);

    $normativa = [];
    foreach (($normativabatch[$questionid] ?? []) as $link) {
        $article = $DB->get_record('local_normativa_articulos', ['id' => $link->articuloid],
            'id,fuente_id,numero_articulo,titulo,contenido_texto,hash_contenido,estado,timemodified');
        if (!$article) {
            continue;
        }
        $normativa[] = [
            'articleid' => (int) $article->id,
            'source' => (string) $link->fuente_titulo,
            'article' => (string) $article->numero_articulo,
            'title' => (string) $article->titulo,
            'text' => (string) $article->contenido_texto,
            'status' => (string) $article->estado,
            'source_url' => (string) ($link->url_boe ?? ''),
            'content_sha256' => (string) ($article->hash_contenido ?? ''),
            'last_modified' => (int) $article->timemodified,
        ];
    }

    $packets[] = [
        'contenttype' => 'local_cp_question',
        'questionid' => (int) $question->id,
        'case' => [
            'id' => (int) $case->id,
            'name' => (string) $case->name,
            'statement' => (string) $case->statement,
        ],
        'question' => [
            'questionid' => (int) $question->id,
            'questiontext' => (string) $question->questiontext,
            'generalfeedback' => (string) ($question->generalfeedback ?? ''),
            'reasoning' => (string) ($question->reasoning ?? ''),
            'modelanswer' => (string) ($question->modelanswer ?? ''),
            'answers' => $answers,
        ],
        'review_source' => $snapshot,
        'source_sha256' => feedback_review_snapshot::hash($snapshot),
        'linked_normativa' => $normativa,
        'review_instruction' => 'Use docs/feedback-review-prompt.md and return one strict JSON object.',
    ];
}

$output = (string) $options['output'];
$json = json_encode($packets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($output, $json . PHP_EOL, LOCK_EX) === false) {
    cli_error('Could not write output: ' . $output);
}
cli_writeln('Exported ' . count($packets) . ' packets to ' . $output);
