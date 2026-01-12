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
 * Filter manager for advanced case filtering.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_manager {

    /** @var array Available filter fields. */
    const FILTER_FIELDS = [
        'categoryid',
        'status',
        'difficulty',
        'createdby',
        'search',
        'tags',
        'date_from',
        'date_to',
        'question_count_min',
        'question_count_max',
    ];

    /** @var array Available sort fields. */
    const SORT_FIELDS = [
        'name' => 'name',
        'timecreated' => 'timecreated',
        'timemodified' => 'timemodified',
        'difficulty' => 'difficulty',
        'status' => 'status',
        'questioncount' => 'questioncount', // Virtual field.
    ];

    /**
     * Get filtered cases with pagination.
     *
     * @param array $filters Filter parameters.
     * @param string $sort Sort field.
     * @param string $order Sort order (ASC/DESC).
     * @param int $page Page number (0-based).
     * @param int $perpage Items per page.
     * @return array Array with 'cases', 'total', 'pages'.
     */
    public static function get_filtered_cases(
        array $filters = [],
        string $sort = 'timemodified',
        string $order = 'DESC',
        int $page = 0,
        int $perpage = 25
    ): array {
        global $DB;

        // Build WHERE clause.
        $where = ['1=1'];
        $params = [];

        // Category filter.
        if (!empty($filters['categoryid'])) {
            $where[] = 'c.categoryid = :categoryid';
            $params['categoryid'] = $filters['categoryid'];
        }

        // Status filter (can be array).
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                list($insql, $inparams) = $DB->get_in_or_equal($filters['status'], SQL_PARAMS_NAMED, 'status');
                $where[] = "c.status $insql";
                $params = array_merge($params, $inparams);
            } else {
                $where[] = 'c.status = :status';
                $params['status'] = $filters['status'];
            }
        }

        // Difficulty filter (can be range).
        if (isset($filters['difficulty']) && $filters['difficulty'] !== '') {
            if (is_array($filters['difficulty'])) {
                list($insql, $inparams) = $DB->get_in_or_equal($filters['difficulty'], SQL_PARAMS_NAMED, 'diff');
                $where[] = "c.difficulty $insql";
                $params = array_merge($params, $inparams);
            } else {
                $where[] = 'c.difficulty = :difficulty';
                $params['difficulty'] = $filters['difficulty'];
            }
        }

        // Created by filter.
        if (!empty($filters['createdby'])) {
            $where[] = 'c.createdby = :createdby';
            $params['createdby'] = $filters['createdby'];
        }

        // Text search (name and statement).
        if (!empty($filters['search'])) {
            $searchterm = '%' . $DB->sql_like_escape($filters['search']) . '%';
            $where[] = '(' . $DB->sql_like('c.name', ':search1', false) . ' OR ' .
                       $DB->sql_like('c.statement', ':search2', false) . ')';
            $params['search1'] = $searchterm;
            $params['search2'] = $searchterm;
        }

        // Tags filter (JSON contains).
        if (!empty($filters['tags'])) {
            $tagconditions = [];
            foreach ((array)$filters['tags'] as $i => $tag) {
                $tagconditions[] = $DB->sql_like('c.tags', ':tag' . $i, false);
                $params['tag' . $i] = '%"' . $DB->sql_like_escape($tag) . '"%';
            }
            $where[] = '(' . implode(' OR ', $tagconditions) . ')';
        }

        // Date range filters.
        if (!empty($filters['date_from'])) {
            $where[] = 'c.timecreated >= :datefrom';
            $params['datefrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'c.timecreated <= :dateto';
            $params['dateto'] = $filters['date_to'];
        }

        $wheresql = implode(' AND ', $where);

        // Build ORDER BY clause.
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $validsortfields = ['name', 'timecreated', 'timemodified', 'difficulty', 'status'];

        if ($sort === 'questioncount') {
            // Special handling for question count sort.
            $ordersql = "questioncount $order, c.name ASC";
        } else if (in_array($sort, $validsortfields)) {
            $ordersql = "c.$sort $order";
        } else {
            $ordersql = "c.timemodified DESC";
        }

        // Main query with question count (optimized - uses LEFT JOIN instead of correlated subquery).
        $sql = "SELECT c.*,
                       cat.name AS categoryname,
                       u.firstname, u.lastname,
                       COALESCE(qc.questioncount, 0) AS questioncount
                  FROM {local_cp_cases} c
                  JOIN {local_cp_categories} cat ON c.categoryid = cat.id
             LEFT JOIN {user} u ON c.createdby = u.id
             LEFT JOIN (SELECT caseid, COUNT(*) AS questioncount
                        FROM {local_cp_questions}
                        GROUP BY caseid) qc ON qc.caseid = c.id
                 WHERE $wheresql
              ORDER BY $ordersql";

        // Count total.
        $countsql = "SELECT COUNT(*)
                       FROM {local_cp_cases} c
                      WHERE $wheresql";
        $total = $DB->count_records_sql($countsql, $params);

        // Get paginated results.
        $cases = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        // Apply question count filters after query (subquery filter is complex).
        if (!empty($filters['question_count_min']) || !empty($filters['question_count_max'])) {
            $cases = array_filter($cases, function($case) use ($filters) {
                if (!empty($filters['question_count_min']) && $case->questioncount < $filters['question_count_min']) {
                    return false;
                }
                if (!empty($filters['question_count_max']) && $case->questioncount > $filters['question_count_max']) {
                    return false;
                }
                return true;
            });
        }

        // Process tags for display.
        foreach ($cases as $case) {
            $case->tags_array = $case->tags ? json_decode($case->tags, true) : [];
            $case->creatorname = trim(($case->firstname ?? '') . ' ' . ($case->lastname ?? ''));
        }

        return [
            'cases' => array_values($cases),
            'total' => $total,
            'pages' => ceil($total / $perpage),
            'page' => $page,
            'perpage' => $perpage,
        ];
    }

    /**
     * Get available filter options for UI.
     *
     * @return array Filter options.
     */
    public static function get_filter_options(): array {
        global $DB;

        // Try to get from cache first.
        $cache = \cache::make('local_casospracticos', 'filteroptions');
        $cached = $cache->get('options');
        if ($cached !== false) {
            return $cached;
        }

        // Get categories.
        $categories = $DB->get_records('local_cp_categories', [], 'name ASC', 'id, name, parent');
        $categoryoptions = [];
        foreach ($categories as $cat) {
            $categoryoptions[] = ['value' => $cat->id, 'label' => $cat->name];
        }

        // Get statuses.
        $statuses = [
            ['value' => 'draft', 'label' => get_string('status_draft', 'local_casospracticos')],
            ['value' => 'pending_review', 'label' => get_string('status_pending_review', 'local_casospracticos')],
            ['value' => 'in_review', 'label' => get_string('status_in_review', 'local_casospracticos')],
            ['value' => 'approved', 'label' => get_string('status_approved', 'local_casospracticos')],
            ['value' => 'published', 'label' => get_string('status_published', 'local_casospracticos')],
            ['value' => 'archived', 'label' => get_string('status_archived', 'local_casospracticos')],
        ];

        // Get difficulties.
        $difficulties = [];
        for ($i = 1; $i <= 5; $i++) {
            $difficulties[] = [
                'value' => $i,
                'label' => get_string('difficulty' . $i, 'local_casospracticos'),
            ];
        }

        // Get all unique tags (optimized - only fetch non-empty tags).
        $alltags = [];
        $tagsonly = $DB->get_fieldset_select('local_cp_cases', 'tags', "tags IS NOT NULL AND tags != ''");
        foreach ($tagsonly as $tagsjson) {
            $tags = json_decode($tagsjson, true);
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $alltags[$tag] = true; // Use keys to avoid duplicates.
                }
            }
        }
        $alltags = array_keys($alltags);
        sort($alltags);
        $tagoptions = array_map(fn($t) => ['value' => $t, 'label' => $t], $alltags);

        // Get creators with cases.
        $creators = $DB->get_records_sql("
            SELECT DISTINCT u.id, u.firstname, u.lastname
              FROM {user} u
              JOIN {local_cp_cases} c ON c.createdby = u.id
          ORDER BY u.lastname, u.firstname
        ");
        $creatoroptions = [];
        foreach ($creators as $creator) {
            $creatoroptions[] = [
                'value' => $creator->id,
                'label' => fullname($creator),
            ];
        }

        $result = [
            'categories' => $categoryoptions,
            'statuses' => $statuses,
            'difficulties' => $difficulties,
            'tags' => $tagoptions,
            'creators' => $creatoroptions,
        ];

        // Cache for future requests.
        $cache->set('options', $result);

        return $result;
    }

    /**
     * Build URL with filter parameters.
     *
     * @param \moodle_url $baseurl Base URL.
     * @param array $filters Current filters.
     * @return \moodle_url URL with parameters.
     */
    public static function build_filter_url(\moodle_url $baseurl, array $filters): \moodle_url {
        $url = new \moodle_url($baseurl);

        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                if (is_array($value)) {
                    $url->param($key, implode(',', $value));
                } else {
                    $url->param($key, $value);
                }
            }
        }

        return $url;
    }

    /**
     * Parse filters from request.
     *
     * @return array Parsed filters.
     */
    public static function parse_filters_from_request(): array {
        $filters = [];

        $categoryid = optional_param('categoryid', 0, PARAM_INT);
        if ($categoryid) {
            $filters['categoryid'] = $categoryid;
        }

        $status = optional_param('status', '', PARAM_ALPHANUMEXT);
        if ($status) {
            // Support comma-separated multiple statuses.
            $filters['status'] = strpos($status, ',') !== false ? explode(',', $status) : $status;
        }

        $difficulty = optional_param('difficulty', '', PARAM_TEXT);
        if ($difficulty !== '') {
            $filters['difficulty'] = strpos($difficulty, ',') !== false ?
                array_map('intval', explode(',', $difficulty)) : (int)$difficulty;
        }

        $createdby = optional_param('createdby', 0, PARAM_INT);
        if ($createdby) {
            $filters['createdby'] = $createdby;
        }

        $search = optional_param('search', '', PARAM_TEXT);
        if ($search) {
            $filters['search'] = $search;
        }

        $tags = optional_param('tags', '', PARAM_TEXT);
        if ($tags) {
            $filters['tags'] = explode(',', $tags);
        }

        $datefrom = optional_param('date_from', 0, PARAM_INT);
        if ($datefrom) {
            $filters['date_from'] = $datefrom;
        }

        $dateto = optional_param('date_to', 0, PARAM_INT);
        if ($dateto) {
            $filters['date_to'] = $dateto;
        }

        return $filters;
    }

    /**
     * Get sort parameters from request.
     *
     * @return array ['sort' => string, 'order' => string]
     */
    public static function parse_sort_from_request(): array {
        $sort = optional_param('sort', 'timemodified', PARAM_ALPHANUMEXT);
        $order = optional_param('order', 'DESC', PARAM_ALPHA);

        // Security: Whitelist validation for sort field to prevent SQL injection.
        $allowedsorts = ['name', 'timecreated', 'timemodified', 'difficulty', 'status', 'questioncount'];
        if (!in_array($sort, $allowedsorts)) {
            $sort = 'timemodified';
        }

        return [
            'sort' => $sort,
            'order' => strtoupper($order) === 'ASC' ? 'ASC' : 'DESC',
        ];
    }
}
