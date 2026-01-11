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

require_once($CFG->libdir . '/pdflib.php');

/**
 * PDF exporter for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_exporter {

    /** @var \pdf PDF object. */
    protected $pdf;

    /** @var array Export options. */
    protected $options;

    /**
     * Constructor.
     *
     * @param array $options Export options.
     */
    public function __construct(array $options = []) {
        $this->options = array_merge([
            'include_answers' => true,
            'include_correct' => true,
            'include_feedback' => false,
            'page_break_per_case' => true,
            'show_difficulty' => true,
            'show_category' => true,
            'title' => get_string('practicalcases', 'local_casospracticos'),
        ], $options);

        $this->init_pdf();
    }

    /**
     * Initialize PDF document.
     */
    protected function init_pdf(): void {
        $this->pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');

        $this->pdf->SetCreator('Moodle Practical Cases');
        $this->pdf->SetAuthor(fullname($GLOBALS['USER']));
        $this->pdf->SetTitle($this->options['title']);

        $this->pdf->SetMargins(15, 20, 15);
        $this->pdf->SetAutoPageBreak(true, 20);

        $this->pdf->SetFont('helvetica', '', 11);
    }

    /**
     * Export a single case to PDF.
     *
     * @param int $caseid Case ID.
     * @return string PDF content.
     */
    public function export_case(int $caseid): string {
        global $DB;

        $case = case_manager::get_with_questions($caseid);
        if (!$case) {
            throw new \moodle_exception('casenotfound', 'local_casospracticos');
        }

        $this->pdf->AddPage();
        $this->render_case($case);

        return $this->pdf->Output('', 'S');
    }

    /**
     * Export multiple cases to PDF.
     *
     * @param array $caseids Array of case IDs.
     * @return string PDF content.
     */
    public function export_cases(array $caseids): string {
        global $DB;

        $first = true;
        foreach ($caseids as $caseid) {
            $case = case_manager::get_with_questions($caseid);
            if (!$case) {
                continue;
            }

            if (!$first && $this->options['page_break_per_case']) {
                $this->pdf->AddPage();
            } else if ($first) {
                $this->pdf->AddPage();
                $first = false;
            }

            $this->render_case($case);

            if (!$this->options['page_break_per_case']) {
                $this->pdf->Ln(10);
            }
        }

        return $this->pdf->Output('', 'S');
    }

    /**
     * Export cases by category to PDF.
     *
     * @param int $categoryid Category ID (0 for all).
     * @return string PDF content.
     */
    public function export_category(int $categoryid = 0): string {
        global $DB;

        if ($categoryid) {
            $cases = $DB->get_records('local_cp_cases', ['categoryid' => $categoryid, 'status' => 'published'], 'name ASC');
        } else {
            $cases = $DB->get_records('local_cp_cases', ['status' => 'published'], 'categoryid ASC, name ASC');
        }

        return $this->export_cases(array_keys($cases));
    }

    /**
     * Render a case to PDF.
     *
     * @param object $case Case object with questions.
     */
    protected function render_case(object $case): void {
        global $DB;

        // Case header.
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetTextColor(0, 51, 102);
        $this->pdf->Cell(0, 10, $case->name, 0, 1);

        // Metadata line.
        if ($this->options['show_category'] || $this->options['show_difficulty']) {
            $this->pdf->SetFont('helvetica', 'I', 9);
            $this->pdf->SetTextColor(128, 128, 128);

            $meta = [];
            if ($this->options['show_category']) {
                $category = $DB->get_record('local_cp_categories', ['id' => $case->categoryid]);
                if ($category) {
                    $meta[] = get_string('category') . ': ' . $category->name;
                }
            }
            if ($this->options['show_difficulty'] && $case->difficulty) {
                $meta[] = get_string('difficulty', 'local_casospracticos') . ': ' .
                         get_string('difficulty' . $case->difficulty, 'local_casospracticos');
            }
            $meta[] = count($case->questions) . ' ' . get_string('questions', 'local_casospracticos');

            $this->pdf->Cell(0, 6, implode(' | ', $meta), 0, 1);
        }

        $this->pdf->Ln(3);

        // Case statement.
        $this->pdf->SetFont('helvetica', '', 11);
        $this->pdf->SetTextColor(0, 0, 0);

        $statement = strip_tags(format_text($case->statement, $case->statementformat));
        $this->pdf->MultiCell(0, 6, $statement, 0, 'J');

        $this->pdf->Ln(5);

        // Questions.
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->Line(15, $this->pdf->GetY(), 195, $this->pdf->GetY());
        $this->pdf->Ln(5);

        $qnum = 1;
        foreach ($case->questions as $question) {
            $this->render_question($question, $qnum);
            $qnum++;
        }
    }

    /**
     * Render a question to PDF.
     *
     * @param object $question Question object with answers.
     * @param int $number Question number.
     */
    protected function render_question(object $question, int $number): void {
        // Question text.
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->SetTextColor(0, 0, 0);

        $questiontext = strip_tags(format_text($question->questiontext, $question->questiontextformat));
        $this->pdf->MultiCell(0, 6, "$number. $questiontext", 0, 'L');

        $this->pdf->Ln(2);

        // Answers.
        if ($this->options['include_answers'] && !empty($question->answers)) {
            $this->pdf->SetFont('helvetica', '', 10);
            $letters = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
            $anum = 0;

            foreach ($question->answers as $answer) {
                $letter = $letters[$anum] ?? ($anum + 1);
                $answertext = strip_tags($answer->answer);

                // Mark correct answer if enabled.
                if ($this->options['include_correct'] && $answer->fraction > 0) {
                    $this->pdf->SetFont('helvetica', 'B', 10);
                    $this->pdf->SetTextColor(0, 128, 0);
                    $prefix = "($letter) ✓ ";
                } else {
                    $this->pdf->SetFont('helvetica', '', 10);
                    $this->pdf->SetTextColor(64, 64, 64);
                    $prefix = "($letter) ";
                }

                $this->pdf->Cell(10, 5, '', 0, 0); // Indent.
                $this->pdf->MultiCell(0, 5, $prefix . $answertext, 0, 'L');

                // Feedback.
                if ($this->options['include_feedback'] && !empty($answer->feedback)) {
                    $this->pdf->SetFont('helvetica', 'I', 9);
                    $this->pdf->SetTextColor(128, 128, 128);
                    $this->pdf->Cell(15, 5, '', 0, 0);
                    $this->pdf->MultiCell(0, 5, strip_tags($answer->feedback), 0, 'L');
                }

                $anum++;
            }
        }

        $this->pdf->Ln(5);
    }

    /**
     * Send PDF to browser for download.
     *
     * @param string $content PDF content.
     * @param string $filename Filename.
     */
    public static function send_to_browser(string $content, string $filename = 'cases.pdf'): void {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $content;
        exit;
    }
}
