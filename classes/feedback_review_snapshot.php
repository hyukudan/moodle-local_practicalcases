<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Canonical snapshot of every input used to review a practical-case solution.
 */
class feedback_review_snapshot {
    /**
     * Build a deterministic snapshot.
     *
     * @param int $questionid Local practical-case question ID.
     * @return array
     */
    public static function build(int $questionid): array {
        global $DB;

        $question = question_manager::get_with_answers($questionid);
        if (!$question) {
            throw new \moodle_exception('error:questionnotfound', 'local_casospracticos');
        }
        $case = $DB->get_record('local_cp_cases', ['id' => $question->caseid], '*', MUST_EXIST);

        $questionfields = [
            'id', 'caseid', 'questiontext', 'questiontextformat', 'qtype', 'defaultmark', 'sortorder',
            'generalfeedback', 'generalfeedbackformat', 'reasoning', 'reasoningformat', 'modelanswer',
            'modelanswerformat', 'feedbackstatus', 'feedbackverifiedat', 'single', 'shuffleanswers',
            'timecreated', 'timemodified',
        ];
        $answerfields = [
            'id', 'questionid', 'answer', 'answerformat', 'fraction', 'feedback', 'feedbackformat', 'sortorder',
        ];
        // Do not include case.timemodified: updating one reviewed question
        // touches its parent case and would stale every later question in the
        // same atomic batch. The actual case inputs are included directly.
        $casefields = ['id', 'name', 'statement', 'statementformat', 'status'];

        $snapshot = [
            'contenttype' => 'local_cp_question',
            'case' => self::pick($case, $casefields),
            'question' => self::pick($question, $questionfields),
            'answers' => [],
            'normativa' => [],
        ];
        foreach (array_values($question->answers) as $answer) {
            $snapshot['answers'][] = self::pick($answer, $answerfields);
        }

        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_cp_question_normativa')
                && $dbman->table_exists('local_normativa_articulos')
                && $dbman->table_exists('local_normativa_fuentes')) {
            $sql = "SELECT qn.id AS linkid, qn.relevancia,
                           a.id AS articleid, a.numero_articulo, a.titulo AS article_title,
                           a.contenido_texto, a.hash_contenido AS article_hash,
                           a.estado AS article_status, a.timemodified AS article_modified,
                           f.id AS sourceid, f.codigo_boe, f.codigo_boib,
                           f.titulo AS source_title, f.titulo_corto AS source_short_title,
                           f.url_boe, f.estado AS source_status, f.hash_contenido AS source_hash,
                           f.sync_estado, f.last_success_at
                      FROM {local_cp_question_normativa} qn
                      JOIN {local_normativa_articulos} a ON a.id = qn.articulo_id
                      JOIN {local_normativa_fuentes} f ON f.id = a.fuente_id
                     WHERE qn.questionid = :questionid
                  ORDER BY f.id, a.orden, a.id";
            foreach ($DB->get_records_sql($sql, ['questionid' => $questionid]) as $row) {
                $snapshot['normativa'][] = (array) $row;
            }
        }

        return $snapshot;
    }

    /**
     * Hash a canonical snapshot.
     *
     * @param array $snapshot Snapshot from build().
     * @return string
     */
    public static function hash(array $snapshot): string {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Copy named object properties into an ordered array.
     *
     * @param object $record Record.
     * @param array $fields Field names.
     * @return array
     */
    private static function pick(object $record, array $fields): array {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $record->{$field} ?? null;
        }
        return $result;
    }
}
