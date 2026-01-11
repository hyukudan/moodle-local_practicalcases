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
 * Rate limiter for web service API calls.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Rate limiter class using Moodle's cache API.
 *
 * Implements a sliding window rate limiting algorithm.
 */
class rate_limiter {

    /** @var int Default requests per minute for read operations */
    const DEFAULT_READ_LIMIT = 60;

    /** @var int Default requests per minute for write operations */
    const DEFAULT_WRITE_LIMIT = 30;

    /** @var int Time window in seconds (1 minute) */
    const WINDOW_SIZE = 60;

    /** @var \cache Cache instance */
    private $cache;

    /** @var int User ID */
    private $userid;

    /**
     * Constructor.
     *
     * @param int|null $userid User ID (defaults to current user)
     */
    public function __construct(?int $userid = null) {
        global $USER;

        $this->userid = $userid ?? $USER->id;
        $this->cache = \cache::make('local_casospracticos', 'ratelimit');
    }

    /**
     * Check if a request is allowed under rate limiting.
     *
     * @param string $operation Operation name (used for grouping)
     * @param string $type Operation type ('read' or 'write')
     * @return bool True if request is allowed
     */
    public function is_allowed(string $operation, string $type = 'read'): bool {
        // Check if rate limiting is enabled.
        if (!get_config('local_casospracticos', 'enableratelimiting')) {
            return true;
        }

        // Site admins bypass rate limiting.
        if (is_siteadmin($this->userid)) {
            return true;
        }

        $limit = $this->get_limit($type);
        $key = $this->get_cache_key($operation);
        $currenttime = time();

        // Get current request log.
        $requests = $this->cache->get($key);
        if ($requests === false) {
            $requests = [];
        }

        // Clean old requests outside the window.
        $windowstart = $currenttime - self::WINDOW_SIZE;
        $requests = array_filter($requests, function($timestamp) use ($windowstart) {
            return $timestamp >= $windowstart;
        });

        // Check if under limit.
        if (count($requests) >= $limit) {
            // Log rate limit hit for monitoring.
            $this->log_rate_limit_hit($operation, $type, count($requests));
            return false;
        }

        // Add current request.
        $requests[] = $currenttime;
        $this->cache->set($key, $requests);

        return true;
    }

    /**
     * Check rate limit and throw exception if exceeded.
     *
     * @param string $operation Operation name
     * @param string $type Operation type ('read' or 'write')
     * @throws \moodle_exception If rate limit exceeded
     */
    public function check(string $operation, string $type = 'read'): void {
        if (!$this->is_allowed($operation, $type)) {
            throw new \moodle_exception('error:ratelimitexceeded', 'local_casospracticos');
        }
    }

    /**
     * Get the rate limit for an operation type.
     *
     * @param string $type Operation type ('read' or 'write')
     * @return int Requests per minute limit
     */
    private function get_limit(string $type): int {
        if ($type === 'write') {
            $limit = get_config('local_casospracticos', 'ratelimit_write');
            return $limit !== false ? (int) $limit : self::DEFAULT_WRITE_LIMIT;
        }

        $limit = get_config('local_casospracticos', 'ratelimit_read');
        return $limit !== false ? (int) $limit : self::DEFAULT_READ_LIMIT;
    }

    /**
     * Get the cache key for a user/operation combination.
     *
     * @param string $operation Operation name
     * @return string Cache key
     */
    private function get_cache_key(string $operation): string {
        return "ratelimit_{$this->userid}_{$operation}";
    }

    /**
     * Log a rate limit hit for monitoring purposes.
     *
     * @param string $operation Operation name
     * @param string $type Operation type
     * @param int $count Current request count
     */
    private function log_rate_limit_hit(string $operation, string $type, int $count): void {
        $event = \local_casospracticos\event\rate_limit_exceeded::create([
            'context' => \context_system::instance(),
            'userid' => $this->userid,
            'other' => [
                'operation' => $operation,
                'type' => $type,
                'count' => $count,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Get remaining requests for an operation.
     *
     * @param string $operation Operation name
     * @param string $type Operation type ('read' or 'write')
     * @return int Remaining requests in current window
     */
    public function get_remaining(string $operation, string $type = 'read'): int {
        if (!get_config('local_casospracticos', 'enableratelimiting')) {
            return PHP_INT_MAX;
        }

        $limit = $this->get_limit($type);
        $key = $this->get_cache_key($operation);
        $currenttime = time();

        $requests = $this->cache->get($key);
        if ($requests === false) {
            return $limit;
        }

        // Clean old requests.
        $windowstart = $currenttime - self::WINDOW_SIZE;
        $requests = array_filter($requests, function($timestamp) use ($windowstart) {
            return $timestamp >= $windowstart;
        });

        return max(0, $limit - count($requests));
    }

    /**
     * Reset rate limit for a user/operation (for testing or admin use).
     *
     * @param string $operation Operation name
     */
    public function reset(string $operation): void {
        $key = $this->get_cache_key($operation);
        $this->cache->delete($key);
    }
}
