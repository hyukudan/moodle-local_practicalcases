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

    /** @var int Number of cases imported */
    private $casesimported = 0;

    /** @var int Number of questions imported */
    private $questionsimported = 0;

    /** @var array Import errors */
    private $errors = [];

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

        $content = file_get_contents($filepath);
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

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

        try {
            if ($format === 'json') {
                $this->import_json($content, $targetcategoryid);
            } else {
                $this->import_xml($content, $targetcategoryid);
            }

            if (empty($this->errors)) {
                return [
                    'success' => true,
                    'cases' => $this->casesimported,
                    'questions' => $this->questionsimported,
                    'errors' => [],
                ];
            } else {
                return [
                    'success' => false,
                    'cases' => $this->casesimported,
                    'questions' => $this->questionsimported,
                    'errors' => $this->errors,
                ];
            }

        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Import from XML content.
     *
     * @param string $content XML content
     * @param int|null $targetcategoryid Target category
     */
    private function import_xml(string $content, ?int $targetcategoryid): void {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if ($xml === false) {
            $errors = libxml_get_errors();
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
            // Create case.
            $casedata = new \stdClass();
            $casedata->categoryid = $categoryid;
            $casedata->name = (string) $casexml->name;
            $casedata->statement = (string) $casexml->statement;
            $casedata->statementformat = (int) ($casexml->statementformat ?? FORMAT_HTML);
            $casedata->status = (string) ($casexml->status ?? 'draft');
            $casedata->difficulty = !empty($casexml->difficulty) ? (int) $casexml->difficulty : null;

            // Tags.
            $tags = [];
            if (isset($casexml->tags)) {
                foreach ($casexml->tags->tag as $tag) {
                    $tags[] = (string) $tag;
                }
            }
            $casedata->tags = $tags;

            $caseid = case_manager::create($casedata);
            $this->casesimported++;

            // Import questions.
            if (isset($casexml->questions)) {
                foreach ($casexml->questions->question as $questionxml) {
                    $this->import_question_from_xml($questionxml, $caseid);
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
        $qdata->qtype = (string) ($questionxml['type'] ?? 'multichoice');
        $qdata->questiontext = (string) $questionxml->text;
        $qdata->questiontextformat = (int) ($questionxml->textformat ?? FORMAT_HTML);
        $qdata->defaultmark = (float) ($questionxml->defaultmark ?? 1.0);
        $qdata->single = (int) ($questionxml->single ?? 1);
        $qdata->shuffleanswers = (int) ($questionxml->shuffleanswers ?? 1);
        $qdata->generalfeedback = (string) ($questionxml->generalfeedback ?? '');

        // Answers.
        $answers = [];
        foreach ($questionxml->answer as $answerxml) {
            $answers[] = [
                'answer' => (string) $answerxml->text,
                'answerformat' => FORMAT_HTML,
                'fraction' => (float) ($answerxml['fraction'] ?? 0),
                'feedback' => (string) ($answerxml->feedback ?? ''),
                'feedbackformat' => FORMAT_HTML,
            ];
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
            $case->name = $casedata['name'] ?? 'Sin nombre';
            $case->statement = $casedata['statement'] ?? '';
            $case->statementformat = $casedata['statementformat'] ?? FORMAT_HTML;
            $case->status = $casedata['status'] ?? 'draft';
            $case->difficulty = $casedata['difficulty'] ?? null;
            $case->tags = $casedata['tags'] ?? [];

            $caseid = case_manager::create($case);
            $this->casesimported++;

            // Import questions.
            foreach ($casedata['questions'] ?? [] as $qdata) {
                $this->import_question_from_array($qdata, $caseid);
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
        $question->qtype = $qdata['type'] ?? 'multichoice';
        $question->questiontext = $qdata['text'] ?? '';
        $question->questiontextformat = $qdata['textformat'] ?? FORMAT_HTML;
        $question->defaultmark = $qdata['defaultmark'] ?? 1.0;
        $question->single = $qdata['single'] ?? 1;
        $question->shuffleanswers = $qdata['shuffleanswers'] ?? 1;
        $question->generalfeedback = $qdata['generalfeedback'] ?? '';

        // Answers.
        $answers = [];
        foreach ($qdata['answers'] ?? [] as $adata) {
            $answers[] = [
                'answer' => $adata['text'] ?? '',
                'answerformat' => FORMAT_HTML,
                'fraction' => $adata['fraction'] ?? 0,
                'feedback' => $adata['feedback'] ?? '',
                'feedbackformat' => FORMAT_HTML,
            ];
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
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($content);
                if ($xml === false) {
                    $errors = libxml_get_errors();
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
