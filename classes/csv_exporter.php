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

namespace local_casospracticos;

/**
 * CSV exporter for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_exporter {

    /** @var string CSV delimiter. */
    protected $delimiter = ',';

    /** @var string Text enclosure. */
    protected $enclosure = '"';

    /** @var array Export options. */
    protected $options;

    /**
     * Constructor.
     *
     * @param array $options Export options.
     */
    public function __construct(array $options = []) {
        $this->options = array_merge([
            'include_questions' => true,
            'include_answers' => true,
            'flatten' => false, // If true, one row per question.
            'delimiter' => ',',
        ], $options);

        $this->delimiter = $this->options['delimiter'];
    }

    /**
     * Export cases to CSV.
     *
     * @param array $caseids Array of case IDs (empty for all).
     * @return string CSV content.
     */
    public function export(array $caseids = []): string {
        global $DB;

        if (empty($caseids)) {
            $cases = $DB->get_records('local_cp_cases', [], 'categoryid ASC, name ASC');
            $caseids = array_keys($cases);
        }

        if ($this->options['flatten']) {
            return $this->export_flat($caseids);
        } else {
            return $this->export_hierarchical($caseids);
        }
    }

    /**
     * Export in flat format (one row per question).
     *
     * @param array $caseids Case IDs.
     * @return string CSV content.
     */
    protected function export_flat(array $caseids): string {
        global $DB;

        $output = fopen('php://temp', 'r+');

        // Header row.
        $headers = [
            'case_id',
            'case_name',
            'category',
            'status',
            'difficulty',
            'statement',
            'question_number',
            'question_text',
            'question_type',
            'answer_a',
            'answer_b',
            'answer_c',
            'answer_d',
            'correct_answer',
        ];

        fputcsv($output, $headers, $this->delimiter, $this->enclosure);

        foreach ($caseids as $caseid) {
            $case = case_manager::get_with_questions($caseid);
            if (!$case) {
                continue;
            }

            $category = $DB->get_record('local_cp_categories', ['id' => $case->categoryid]);
            $categoryname = $category ? $category->name : '';

            $qnum = 1;
            foreach ($case->questions as $question) {
                $answers = ['', '', '', ''];
                $correct = '';
                $letters = ['A', 'B', 'C', 'D'];
                $anum = 0;

                foreach ($question->answers as $answer) {
                    if ($anum < 4) {
                        $answers[$anum] = strip_tags($answer->answer);
                        if ($answer->fraction > 0) {
                            $correct .= $letters[$anum];
                        }
                    }
                    $anum++;
                }

                $row = [
                    $case->id,
                    $case->name,
                    $categoryname,
                    $case->status,
                    $case->difficulty,
                    strip_tags($case->statement),
                    $qnum,
                    strip_tags($question->questiontext),
                    $question->qtype,
                    $answers[0],
                    $answers[1],
                    $answers[2],
                    $answers[3],
                    $correct,
                ];

                fputcsv($output, $row, $this->delimiter, $this->enclosure);
                $qnum++;
            }
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Export in hierarchical format (one row per case).
     *
     * @param array $caseids Case IDs.
     * @return string CSV content.
     */
    protected function export_hierarchical(array $caseids): string {
        global $DB;

        $output = fopen('php://temp', 'r+');

        // Header row.
        $headers = [
            'id',
            'name',
            'category',
            'category_id',
            'status',
            'difficulty',
            'statement',
            'question_count',
            'tags',
            'created_by',
            'created_at',
            'modified_at',
        ];

        if ($this->options['include_questions']) {
            $headers[] = 'questions_json';
        }

        fputcsv($output, $headers, $this->delimiter, $this->enclosure);

        foreach ($caseids as $caseid) {
            $case = $this->options['include_questions'] ?
                case_manager::get_with_questions($caseid) :
                case_manager::get($caseid);

            if (!$case) {
                continue;
            }

            $category = $DB->get_record('local_cp_categories', ['id' => $case->categoryid]);
            $creator = $DB->get_record('user', ['id' => $case->createdby]);

            $row = [
                $case->id,
                $case->name,
                $category ? $category->name : '',
                $case->categoryid,
                $case->status,
                $case->difficulty,
                strip_tags($case->statement),
                isset($case->questions) ? count($case->questions) : 0,
                $case->tags ?? '',
                $creator ? fullname($creator) : '',
                date('Y-m-d H:i:s', $case->timecreated),
                date('Y-m-d H:i:s', $case->timemodified),
            ];

            if ($this->options['include_questions'] && isset($case->questions)) {
                $questionsdata = [];
                foreach ($case->questions as $q) {
                    $qdata = [
                        'text' => strip_tags($q->questiontext),
                        'type' => $q->qtype,
                        'answers' => [],
                    ];
                    foreach ($q->answers as $a) {
                        $qdata['answers'][] = [
                            'text' => strip_tags($a->answer),
                            'correct' => $a->fraction > 0,
                        ];
                    }
                    $questionsdata[] = $qdata;
                }
                $row[] = json_encode($questionsdata, JSON_UNESCAPED_UNICODE);
            }

            fputcsv($output, $row, $this->delimiter, $this->enclosure);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Export audit log to CSV.
     *
     * @param array $filters Log filters.
     * @return string CSV content.
     */
    public function export_audit_log(array $filters = []): string {
        $result = audit_logger::get_all_logs($filters, 0, 10000);

        $output = fopen('php://temp', 'r+');

        $headers = [
            'id',
            'object_type',
            'object_id',
            'action',
            'user_id',
            'user_name',
            'ip_address',
            'timestamp',
            'changes',
        ];

        fputcsv($output, $headers, $this->delimiter, $this->enclosure);

        foreach ($result['logs'] as $log) {
            $row = [
                $log->id,
                $log->objecttype,
                $log->objectid,
                $log->action,
                $log->userid,
                isset($log->user) ? fullname($log->user) : '',
                $log->ipaddress,
                date('Y-m-d H:i:s', $log->timecreated),
                $log->changes ?? '',
            ];

            fputcsv($output, $row, $this->delimiter, $this->enclosure);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Send CSV to browser for download.
     *
     * @param string $content CSV content.
     * @param string $filename Filename.
     */
    public static function send_to_browser(string $content, string $filename = 'cases.csv'): void {
        // Add BOM for Excel UTF-8 compatibility.
        $content = "\xEF\xBB\xBF" . $content;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $content;
        exit;
    }
}
