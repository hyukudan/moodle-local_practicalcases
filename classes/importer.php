<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Importer for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to import practical cases from XML/JSON formats.
 */
class importer {

    /** @var int Maximum content length (10MB default, configurable). */
    const MAX_CONTENT_LENGTH = 10485760;

    /** @var int Maximum case name length. */
    const MAX_NAME_LENGTH = 255;

    /** @var int Maximum number of cases per import. */
    const MAX_CASES_PER_IMPORT = 500;

    /** @var int Maximum number of questions per case. */
    const MAX_QUESTIONS_PER_CASE = 100;

    /** @var int Maximum number of answers per question. */
    const MAX_ANSWERS_PER_QUESTION = 20;

    /** @var array Valid status values. */
    const VALID_STATUSES = ['draft', 'pending_review', 'in_review', 'approved', 'published', 'archived'];

    /** @var array Valid question types. */
    const VALID_QTYPES = ['multichoice', 'truefalse', 'shortanswer', 'matching'];

    /** @var int Number of cases imported */
    private $casesimported = 0;

    /** @var int Number of questions imported */
    private $questionsimported = 0;

    /** @var array Import errors */
    private $errors = [];

    /** @var array Import warnings (non-fatal). */
    private $warnings = [];

    /**
     * Import from file.
     *
     * @param string $filepath Path to file
     * @param int|null $targetcategoryid Target category (null = create from file)
     * @return array Result with 'success', 'cases', 'questions', 'errors'
     */
    public function import_file(string $filepath, ?int $targetcategoryid = null): array {
        if (!file_exists($filepath)) {
            return $this->error('File not found: ' . $filepath);
        }

        // Validate file extension.
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xml', 'json', 'csv'])) {
            return $this->error('Invalid file extension. Only XML, JSON and CSV are allowed.');
        }

        // Security: Validate MIME type (magic bytes) to prevent file type spoofing.
        $validation = $this->validate_mime_type($filepath, $extension);
        if (!$validation['valid']) {
            return $this->error($validation['error']);
        }

        $content = file_get_contents($filepath);

        return $this->import_content($content, $extension, $targetcategoryid);
    }

    /**
     * Import from content string.
     *
     * @param string $content File content
     * @param string $format Format (xml or json)
     * @param int|null $targetcategoryid Target category
     * @return array Result
     */
    public function import_content(string $content, string $format, ?int $targetcategoryid = null): array {
        $this->casesimported = 0;
        $this->questionsimported = 0;
        $this->errors = [];
        $this->warnings = [];

        // Validate content size.
        $maxsize = get_config('local_casospracticos', 'maximportsize') ?: self::MAX_CONTENT_LENGTH;
        if (strlen($content) > $maxsize) {
            return $this->error('Import file exceeds maximum size of ' . display_size($maxsize));
        }

        // Validate format.
        if (!in_array($format, ['xml', 'json'])) {
            return $this->error('Invalid format. Only XML and JSON are supported.');
        }

        try {
            if ($format === 'json') {
                $this->import_json($content, $targetcategoryid);
            } else {
                $this->import_xml($content, $targetcategoryid);
            }

            return [
                'success' => empty($this->errors),
                'cases' => $this->casesimported,
                'questions' => $this->questionsimported,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
            ];

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Validate MIME type of imported file to prevent spoofing.
     *
     * Security: Validates actual file content (magic bytes), not just extension.
     *
     * @param string $filepath Path to file
     * @param string $extension Expected extension
     * @return array ['valid' => bool, 'error' => string]
     */
    private function validate_mime_type(string $filepath, string $extension): array {
        // Allowed MIME types per extension.
        $allowedmimes = [
            'xml' => ['application/xml', 'text/xml'],
            'json' => ['application/json', 'text/plain'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
        ];

        if (!isset($allowedmimes[$extension])) {
            return ['valid' => false, 'error' => 'Unsupported file type'];
        }

        // Get real MIME type from file content (magic bytes).
        if (!function_exists('finfo_open')) {
            // Fallback: If fileinfo extension not available, skip MIME validation.
            // This is acceptable as extension validation is still in place.
            return ['valid' => true, 'error' => ''];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimetype = finfo_file($finfo, $filepath);
        finfo_close($finfo);

        if ($mimetype === false) {
            return ['valid' => false, 'error' => 'Unable to determine file type'];
        }

        // Check if detected MIME type is allowed for this extension.
        if (!in_array($mimetype, $allowedmimes[$extension])) {
            $expected = implode(', ', $allowedmimes[$extension]);
            return [
                'valid' => false,
                'error' => "Invalid file type. Expected $expected but got $mimetype. " .
                          "File extension does not match file content."
            ];
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Validate a case name.
     *
     * @param string $name Case name
     * @return string Sanitized name
     * @throws \Exception If name is invalid
     */
    private function validate_case_name(string $name): string {
        $name = trim($name);
        if (empty($name)) {
            throw new \Exception('Case name cannot be empty');
        }
        if (strlen($name) > self::MAX_NAME_LENGTH) {
            $name = substr($name, 0, self::MAX_NAME_LENGTH);
            $this->warnings[] = "Case name truncated to " . self::MAX_NAME_LENGTH . " characters";
        }
        return clean_param($name, PARAM_TEXT);
    }

    /**
     * Validate a status value.
     *
     * @param string $status Status to validate
     * @return string Valid status (defaults to 'draft' if invalid)
     */
    private function validate_status(string $status): string {
        $status = strtolower(trim($status));
        if (!in_array($status, self::VALID_STATUSES)) {
            $this->warnings[] = "Invalid status '$status', defaulting to 'draft'";
            return 'draft';
        }
        return $status;
    }

    /**
     * Validate a question type.
     *
     * @param string $qtype Question type
     * @return string Valid qtype (defaults to 'multichoice' if invalid)
     */
    private function validate_qtype(string $qtype): string {
        $qtype = strtolower(trim($qtype));
        if (!in_array($qtype, self::VALID_QTYPES)) {
            $this->warnings[] = "Invalid question type '$qtype', defaulting to 'multichoice'";
            return 'multichoice';
        }
        return $qtype;
    }

    /**
     * Validate difficulty level.
     *
     * @param mixed $difficulty Difficulty value
     * @return int|null Valid difficulty (1-5) or null
     */
    private function validate_difficulty($difficulty): ?int {
        if ($difficulty === null || $difficulty === '') {
            return null;
        }
        $diff = (int) $difficulty;
        if ($diff < 1 || $diff > 5) {
            $this->warnings[] = "Invalid difficulty '$difficulty', setting to null";
            return null;
        }
        return $diff;
    }

    /**
     * Validate fraction value.
     *
     * @param mixed $fraction Fraction value
     * @return float Valid fraction (0-1)
     */
    private function validate_fraction($fraction): float {
        $frac = (float) $fraction;
        // Handle percentage format (0-100 instead of 0-1).
        if ($frac > 1 && $frac <= 100) {
            $frac = $frac / 100;
        }
        return max(0, min(1, $frac));
    }

    /**
     * Import from XML content.
     *
     * @param string $content XML content
     * @param int|null $targetcategoryid Target category
     */
    private function import_xml(string $content, ?int $targetcategoryid): void {
        // Security: Protect against XXE (XML External Entity) attacks.
        // LIBXML_NONET disables network access during parsing.
        // We explicitly don't use LIBXML_NOENT as it would expand entities.
        libxml_use_internal_errors(true);

        // For PHP < 8.0, disable entity loader (function is deprecated in 8.0+).
        $disableentities = false;
        if (\PHP_VERSION_ID < 80000) {
            $disableentities = libxml_disable_entity_loader(true);
        }

        try {
            $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
        } finally {
            // Restore previous state for PHP < 8.0.
            if (\PHP_VERSION_ID < 80000) {
                libxml_disable_entity_loader($disableentities);
            }
        }

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $errorMsg = !empty($errors) ? $errors[0]->message : 'Invalid XML';
            throw new \Exception('XML parsing error: ' . $errorMsg);
        }

        foreach ($xml->category as $categoryxml) {
            $categoryid = $targetcategoryid;

            // Create category if not specified.
            if ($categoryid === null) {
                $categoryid = $this->get_or_create_category((string) $categoryxml->name, (string) $categoryxml->description);
            }

            foreach ($categoryxml->case as $casexml) {
                $this->import_case_from_xml($casexml, $categoryid);
            }
        }

        // Also handle cases at root level (no category wrapper).
        foreach ($xml->case as $casexml) {
            $categoryid = $targetcategoryid ?? $this->get_or_create_category('Importados', '');
            $this->import_case_from_xml($casexml, $categoryid);
        }
    }

    /**
     * Import from JSON content.
     *
     * @param string $content JSON content
     * @param int|null $targetcategoryid Target category
     */
    private function import_json(string $content, ?int $targetcategoryid): void {
        $data = json_decode($content, true);

        if ($data === null) {
            throw new \Exception('JSON parsing error: ' . json_last_error_msg());
        }

        if (!isset($data['categories'])) {
            throw new \Exception('Invalid JSON format: missing categories');
        }

        foreach ($data['categories'] as $categorydata) {
            $categoryid = $targetcategoryid;

            if ($categoryid === null) {
                $categoryid = $this->get_or_create_category(
                    $categorydata['name'] ?? 'Importados',
                    $categorydata['description'] ?? ''
                );
            }

            foreach ($categorydata['cases'] ?? [] as $casedata) {
                $this->import_case_from_array($casedata, $categoryid);
            }
        }
    }

    /**
     * Import a case from XML element.
     *
     * @param \SimpleXMLElement $casexml Case XML
     * @param int $categoryid Category ID
     */
    private function import_case_from_xml(\SimpleXMLElement $casexml, int $categoryid): void {
        try {
            // Validate and create case.
            $casedata = new \stdClass();
            $casedata->categoryid = $categoryid;
            $casedata->name = $this->validate_case_name((string) $casexml->name);
            $casedata->statement = (string) $casexml->statement;
            $casedata->statementformat = (int) ($casexml->statementformat ?? FORMAT_HTML);
            $casedata->status = $this->validate_status((string) ($casexml->status ?? 'draft'));
            $casedata->difficulty = $this->validate_difficulty($casexml->difficulty ?? null);

            // Tags - sanitize each tag.
            $tags = [];
            if (isset($casexml->tags)) {
                foreach ($casexml->tags->tag as $tag) {
                    $tagtext = clean_param(trim((string) $tag), PARAM_TEXT);
                    if (!empty($tagtext)) {
                        $tags[] = $tagtext;
                    }
                }
            }
            $casedata->tags = $tags;

            $caseid = case_manager::create($casedata);
            $this->casesimported++;

            // Import questions with limit check.
            if (isset($casexml->questions)) {
                $questioncount = 0;
                foreach ($casexml->questions->question as $questionxml) {
                    if ($questioncount >= self::MAX_QUESTIONS_PER_CASE) {
                        $this->warnings[] = "Case '{$casedata->name}': Exceeded max questions limit, some questions skipped";
                        break;
                    }
                    $this->import_question_from_xml($questionxml, $caseid);
                    $questioncount++;
                }
            }

        } catch (\Exception $e) {
            $this->errors[] = 'Error importing case "' . ($casexml->name ?? 'unknown') . '": ' . $e->getMessage();
        }
    }

    /**
     * Import a question from XML element.
     *
     * @param \SimpleXMLElement $questionxml Question XML
     * @param int $caseid Case ID
     */
    private function import_question_from_xml(\SimpleXMLElement $questionxml, int $caseid): void {
        $qdata = new \stdClass();
        $qdata->caseid = $caseid;
        $qdata->qtype = $this->validate_qtype((string) ($questionxml['type'] ?? 'multichoice'));
        $qdata->questiontext = (string) $questionxml->text;
        $qdata->questiontextformat = (int) ($questionxml->textformat ?? FORMAT_HTML);
        $qdata->defaultmark = max(0, (float) ($questionxml->defaultmark ?? 1.0));
        $qdata->single = (int) ($questionxml->single ?? 1) ? 1 : 0;
        $qdata->shuffleanswers = (int) ($questionxml->shuffleanswers ?? 1) ? 1 : 0;
        $qdata->generalfeedback = (string) ($questionxml->generalfeedback ?? '');

        // Validate question text is not empty.
        if (empty(trim(strip_tags($qdata->questiontext)))) {
            $this->warnings[] = "Skipped question with empty text in case ID $caseid";
            return;
        }

        // Answers with limit check.
        $answers = [];
        $answercount = 0;
        foreach ($questionxml->answer as $answerxml) {
            if ($answercount >= self::MAX_ANSWERS_PER_QUESTION) {
                $this->warnings[] = "Question has too many answers, some skipped";
                break;
            }
            $answers[] = [
                'answer' => (string) $answerxml->text,
                'answerformat' => FORMAT_HTML,
                'fraction' => $this->validate_fraction($answerxml['fraction'] ?? 0),
                'feedback' => (string) ($answerxml->feedback ?? ''),
                'feedbackformat' => FORMAT_HTML,
            ];
            $answercount++;
        }
        $qdata->answers = $answers;

        question_manager::create($qdata);
        $this->questionsimported++;
    }

    /**
     * Import a case from array (JSON).
     *
     * @param array $casedata Case data
     * @param int $categoryid Category ID
     */
    private function import_case_from_array(array $casedata, int $categoryid): void {
        try {
            $case = new \stdClass();
            $case->categoryid = $categoryid;
            $case->name = $this->validate_case_name($casedata['name'] ?? 'Sin nombre');
            $case->statement = $casedata['statement'] ?? '';
            $case->statementformat = $casedata['statementformat'] ?? FORMAT_HTML;
            $case->status = $this->validate_status($casedata['status'] ?? 'draft');
            $case->difficulty = $this->validate_difficulty($casedata['difficulty'] ?? null);

            // Sanitize tags.
            $tags = [];
            foreach ($casedata['tags'] ?? [] as $tag) {
                $tagtext = clean_param(trim($tag), PARAM_TEXT);
                if (!empty($tagtext)) {
                    $tags[] = $tagtext;
                }
            }
            $case->tags = $tags;

            $caseid = case_manager::create($case);
            $this->casesimported++;

            // Import questions with limit check.
            $questioncount = 0;
            foreach ($casedata['questions'] ?? [] as $qdata) {
                if ($questioncount >= self::MAX_QUESTIONS_PER_CASE) {
                    $this->warnings[] = "Case '{$case->name}': Exceeded max questions limit, some questions skipped";
                    break;
                }
                $this->import_question_from_array($qdata, $caseid);
                $questioncount++;
            }

        } catch (\Exception $e) {
            $this->errors[] = 'Error importing case "' . ($casedata['name'] ?? 'unknown') . '": ' . $e->getMessage();
        }
    }

    /**
     * Import a question from array.
     *
     * @param array $qdata Question data
     * @param int $caseid Case ID
     */
    private function import_question_from_array(array $qdata, int $caseid): void {
        $question = new \stdClass();
        $question->caseid = $caseid;
        $question->qtype = $this->validate_qtype($qdata['type'] ?? 'multichoice');
        $question->questiontext = $qdata['text'] ?? '';
        $question->questiontextformat = $qdata['textformat'] ?? FORMAT_HTML;
        $question->defaultmark = max(0, (float) ($qdata['defaultmark'] ?? 1.0));
        $question->single = (int) ($qdata['single'] ?? 1) ? 1 : 0;
        $question->shuffleanswers = (int) ($qdata['shuffleanswers'] ?? 1) ? 1 : 0;
        $question->generalfeedback = $qdata['generalfeedback'] ?? '';

        // Validate question text is not empty.
        if (empty(trim(strip_tags($question->questiontext)))) {
            $this->warnings[] = "Skipped question with empty text in case ID $caseid";
            return;
        }

        // Answers with limit check.
        $answers = [];
        $answercount = 0;
        foreach ($qdata['answers'] ?? [] as $adata) {
            if ($answercount >= self::MAX_ANSWERS_PER_QUESTION) {
                $this->warnings[] = "Question has too many answers, some skipped";
                break;
            }
            $answers[] = [
                'answer' => $adata['text'] ?? '',
                'answerformat' => FORMAT_HTML,
                'fraction' => $this->validate_fraction($adata['fraction'] ?? 0),
                'feedback' => $adata['feedback'] ?? '',
                'feedbackformat' => FORMAT_HTML,
            ];
            $answercount++;
        }
        $question->answers = $answers;

        question_manager::create($question);
        $this->questionsimported++;
    }

    /**
     * Get or create a category by name.
     *
     * @param string $name Category name
     * @param string $description Description
     * @return int Category ID
     */
    private function get_or_create_category(string $name, string $description = ''): int {
        global $DB;

        // Check if exists.
        $existing = $DB->get_record('local_cp_categories', ['name' => $name, 'parent' => 0]);
        if ($existing) {
            return $existing->id;
        }

        // Create new.
        $data = new \stdClass();
        $data->name = $name;
        $data->description = $description;
        $data->descriptionformat = FORMAT_HTML;
        $data->parent = 0;

        return category_manager::create($data);
    }

    /**
     * Return error result.
     *
     * @param string $message Error message
     * @return array Error result
     */
    private function error(string $message): array {
        return [
            'success' => false,
            'cases' => 0,
            'questions' => 0,
            'errors' => [$message],
        ];
    }

    /**
     * Validate import file before processing.
     *
     * @param string $content File content
     * @param string $format Format
     * @return array Validation result
     */
    public static function validate(string $content, string $format): array {
        try {
            if ($format === 'json') {
                $data = json_decode($content, true);
                if ($data === null) {
                    return ['valid' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
                }
                if (!isset($data['categories'])) {
                    return ['valid' => false, 'error' => 'Invalid format: missing categories'];
                }

                $casecount = 0;
                foreach ($data['categories'] as $cat) {
                    $casecount += count($cat['cases'] ?? []);
                }

                return ['valid' => true, 'cases' => $casecount];

            } else {
                // Security: Protect against XXE (XML External Entity) attacks.
                libxml_use_internal_errors(true);

                // For PHP < 8.0, disable entity loader.
                $disableentities = false;
                if (\PHP_VERSION_ID < 80000) {
                    $disableentities = libxml_disable_entity_loader(true);
                }

                try {
                    $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
                } finally {
                    if (\PHP_VERSION_ID < 80000) {
                        libxml_disable_entity_loader($disableentities);
                    }
                }

                if ($xml === false) {
                    $errors = libxml_get_errors();
                    libxml_clear_errors();
                    return ['valid' => false, 'error' => 'Invalid XML: ' . ($errors[0]->message ?? 'parse error')];
                }

                $casecount = 0;
                foreach ($xml->category as $cat) {
                    $casecount += count($cat->case);
                }
                $casecount += count($xml->case);

                return ['valid' => true, 'cases' => $casecount];
            }
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
