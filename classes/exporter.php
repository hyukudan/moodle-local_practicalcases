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
 * Exporter for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to export practical cases to XML/JSON formats.
 */
class exporter {

    /** @var string Export format XML */
    const FORMAT_XML = 'xml';

    /** @var string Export format JSON */
    const FORMAT_JSON = 'json';

    /**
     * Export cases to XML format.
     *
     * @param array $caseids Array of case IDs to export (empty = all)
     * @param int|null $categoryid Export all cases from this category
     * @return string XML content
     */
    public static function export_xml(array $caseids = [], ?int $categoryid = null): string {
        $cases = self::get_cases_for_export($caseids, $categoryid);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><casospracticos/>');
        $xml->addAttribute('version', '1.0');
        $xml->addAttribute('exported', date('Y-m-d H:i:s'));

        // Group by category.
        $bycategory = [];
        foreach ($cases as $case) {
            $catid = $case->categoryid;
            if (!isset($bycategory[$catid])) {
                $bycategory[$catid] = [
                    'category' => category_manager::get($catid),
                    'cases' => [],
                ];
            }
            $bycategory[$catid]['cases'][] = $case;
        }

        foreach ($bycategory as $data) {
            $categoryxml = $xml->addChild('category');
            $categoryxml->addChild('name', htmlspecialchars($data['category']->name ?? 'Sin categoría'));
            if (!empty($data['category']->description)) {
                $categoryxml->addChild('description', htmlspecialchars($data['category']->description));
            }

            foreach ($data['cases'] as $case) {
                self::add_case_to_xml($categoryxml, $case);
            }
        }

        // Format output nicely.
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    /**
     * Export cases to JSON format.
     *
     * @param array $caseids Array of case IDs to export
     * @param int|null $categoryid Export all cases from this category
     * @return string JSON content
     */
    public static function export_json(array $caseids = [], ?int $categoryid = null): string {
        $cases = self::get_cases_for_export($caseids, $categoryid);

        $export = [
            'version' => '1.0',
            'exported' => date('Y-m-d H:i:s'),
            'categories' => [],
        ];

        // Group by category.
        $bycategory = [];
        foreach ($cases as $case) {
            $catid = $case->categoryid;
            if (!isset($bycategory[$catid])) {
                $category = category_manager::get($catid);
                $bycategory[$catid] = [
                    'name' => $category->name ?? 'Sin categoría',
                    'description' => $category->description ?? '',
                    'cases' => [],
                ];
            }
            $bycategory[$catid]['cases'][] = self::case_to_array($case);
        }

        $export['categories'] = array_values($bycategory);

        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Export a single case.
     *
     * @param int $caseid Case ID
     * @param string $format Export format (xml or json)
     * @return string Exported content
     */
    public static function export_case(int $caseid, string $format = self::FORMAT_XML): string {
        if ($format === self::FORMAT_JSON) {
            return self::export_json([$caseid]);
        }
        return self::export_xml([$caseid]);
    }

    /**
     * Get cases for export.
     *
     * @param array $caseids Specific case IDs
     * @param int|null $categoryid Category filter
     * @return array Cases with questions
     */
    private static function get_cases_for_export(array $caseids, ?int $categoryid): array {
        $cases = [];

        if (!empty($caseids)) {
            foreach ($caseids as $id) {
                $case = case_manager::get_with_questions($id);
                if ($case) {
                    // Load answers for each question.
                    foreach ($case->questions as $question) {
                        $question->answers = question_manager::get_answers($question->id);
                    }
                    $cases[] = $case;
                }
            }
        } else if ($categoryid !== null) {
            $caselist = case_manager::get_by_category($categoryid);
            foreach ($caselist as $case) {
                $fullcase = case_manager::get_with_questions($case->id);
                foreach ($fullcase->questions as $question) {
                    $question->answers = question_manager::get_answers($question->id);
                }
                $cases[] = $fullcase;
            }
        } else {
            // All cases.
            $caselist = case_manager::get_all();
            foreach ($caselist as $case) {
                $fullcase = case_manager::get_with_questions($case->id);
                foreach ($fullcase->questions as $question) {
                    $question->answers = question_manager::get_answers($question->id);
                }
                $cases[] = $fullcase;
            }
        }

        return $cases;
    }

    /**
     * Add a case to XML element.
     *
     * @param \SimpleXMLElement $parent Parent XML element
     * @param object $case Case with questions
     */
    private static function add_case_to_xml(\SimpleXMLElement $parent, object $case): void {
        $casexml = $parent->addChild('case');
        $casexml->addChild('name', htmlspecialchars($case->name));
        $casexml->addChild('status', $case->status);

        // Statement as CDATA.
        $statement = $casexml->addChild('statement');
        self::add_cdata($statement, $case->statement);
        $casexml->addChild('statementformat', $case->statementformat);

        if (!empty($case->difficulty)) {
            $casexml->addChild('difficulty', $case->difficulty);
        }

        $tags = case_manager::decode_tags($case->tags);
        if (!empty($tags)) {
            $tagsxml = $casexml->addChild('tags');
            foreach ($tags as $tag) {
                $tagsxml->addChild('tag', htmlspecialchars($tag));
            }
        }

        // Questions.
        if (!empty($case->questions)) {
            $questionsxml = $casexml->addChild('questions');
            foreach ($case->questions as $question) {
                self::add_question_to_xml($questionsxml, $question);
            }
        }
    }

    /**
     * Add a question to XML element.
     *
     * @param \SimpleXMLElement $parent Parent XML element
     * @param object $question Question with answers
     */
    private static function add_question_to_xml(\SimpleXMLElement $parent, object $question): void {
        $qxml = $parent->addChild('question');
        $qxml->addAttribute('type', $question->qtype);

        $text = $qxml->addChild('text');
        self::add_cdata($text, $question->questiontext);
        $qxml->addChild('textformat', $question->questiontextformat);

        $qxml->addChild('defaultmark', $question->defaultmark);
        $qxml->addChild('single', $question->single);
        $qxml->addChild('shuffleanswers', $question->shuffleanswers);

        if (!empty($question->generalfeedback)) {
            $feedback = $qxml->addChild('generalfeedback');
            self::add_cdata($feedback, $question->generalfeedback);
        }

        // Answers.
        if (!empty($question->answers)) {
            foreach ($question->answers as $answer) {
                $axml = $qxml->addChild('answer');
                $axml->addAttribute('fraction', $answer->fraction);

                $atext = $axml->addChild('text');
                self::add_cdata($atext, $answer->answer);

                if (!empty($answer->feedback)) {
                    $afeedback = $axml->addChild('feedback');
                    self::add_cdata($afeedback, $answer->feedback);
                }
            }
        }
    }

    /**
     * Add CDATA section to XML element.
     *
     * @param \SimpleXMLElement $element XML element
     * @param string $content Content
     */
    private static function add_cdata(\SimpleXMLElement $element, string $content): void {
        $dom = dom_import_simplexml($element);
        $doc = $dom->ownerDocument;
        $dom->appendChild($doc->createCDATASection($content));
    }

    /**
     * Convert case to array for JSON export.
     *
     * @param object $case Case with questions
     * @return array Case array
     */
    private static function case_to_array(object $case): array {
        $data = [
            'name' => $case->name,
            'statement' => $case->statement,
            'statementformat' => $case->statementformat,
            'status' => $case->status,
            'difficulty' => $case->difficulty,
            'tags' => case_manager::decode_tags($case->tags),
            'questions' => [],
        ];

        foreach ($case->questions as $question) {
            $qdata = [
                'type' => $question->qtype,
                'text' => $question->questiontext,
                'textformat' => $question->questiontextformat,
                'defaultmark' => (float) $question->defaultmark,
                'single' => (int) $question->single,
                'shuffleanswers' => (int) $question->shuffleanswers,
                'generalfeedback' => $question->generalfeedback ?? '',
                'answers' => [],
            ];

            foreach ($question->answers as $answer) {
                $qdata['answers'][] = [
                    'text' => $answer->answer,
                    'fraction' => (float) $answer->fraction,
                    'feedback' => $answer->feedback ?? '',
                ];
            }

            $data['questions'][] = $qdata;
        }

        return $data;
    }

    /**
     * Get available export formats.
     *
     * @return array Format options
     */
    public static function get_formats(): array {
        return [
            self::FORMAT_XML => 'XML',
            self::FORMAT_JSON => 'JSON',
        ];
    }

    /**
     * Get MIME type for format.
     *
     * @param string $format Format
     * @return string MIME type
     */
    public static function get_mime_type(string $format): string {
        $types = [
            self::FORMAT_XML => 'application/xml',
            self::FORMAT_JSON => 'application/json',
        ];
        return $types[$format] ?? 'text/plain';
    }

    /**
     * Get file extension for format.
     *
     * @param string $format Format
     * @return string Extension
     */
    public static function get_extension(string $format): string {
        return $format; // xml or json.
    }
}
