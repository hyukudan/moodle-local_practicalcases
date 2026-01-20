# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Moodle local plugin (`local_casospracticos`) for managing practical cases with associated questions. Used for scenario-based learning in law schools, medical education, and business case studies. Supports Moodle 4.4+ and PHP 8.0+.

## Development Commands

### Run PHPUnit Tests
```bash
cd /path/to/moodle
vendor/bin/phpunit --testsuite local_casospracticos_testsuite

# Run a specific test file
vendor/bin/phpunit local/casospracticos/tests/case_manager_test.php
```

### Run Behat Tests
```bash
cd /path/to/moodle
vendor/bin/behat --config /path/to/moodledata_behat/behatrun/behat/behat.yml --tags @local_casospracticos
```

### Check Coding Style
```bash
php admin/cli/check_coding_style.php --path=local/casospracticos
```

### Compile AMD JavaScript
```bash
cd /path/to/moodle
npx grunt amd --force

# Or watch for changes
npx grunt watch
```

AMD source files are in `amd/src/`, compiled output goes to `amd/build/`.

## Architecture

### Manager Pattern

Business logic is organized into static manager classes in `classes/`:

| Manager | Responsibility |
|---------|----------------|
| `case_manager` | CRUD for cases, search, status changes, duplication |
| `question_manager` | CRUD for questions/answers, reordering |
| `category_manager` | Hierarchical category management |
| `bulk_manager` | Mass operations (delete, move, publish, archive) |
| `filter_manager` | Advanced filtering with caching |
| `workflow_manager` | Review submission and approval flow |
| `stats_manager` | Analytics and usage statistics |
| `achievements_manager` | Gamification with 10 achievement types |
| `practice_session_manager` | Secure token-based practice sessions |
| `timed_attempt_manager` | Timed practice mode |

### External API

All web services are defined in `db/services.php` and implemented in `classes/external/api.php`. The AMD module `amd/src/repository.js` provides JavaScript wrappers for AJAX calls.

Key service patterns:
- Read operations require `local/casospracticos:view`
- Write operations require `local/casospracticos:edit` or `:create`
- Delete operations require `local/casospracticos:delete`
- Quiz integration requires `local/casospracticos:insertquiz`

### Database Schema (db/install.xml)

Core tables (prefixed `local_cp_`):
- `categories` - Hierarchical organization (parent field for tree)
- `cases` - Main content with statement, status, difficulty, tags (JSON)
- `questions` - Linked to cases, supports multichoice/truefalse/shortanswer
- `answers` - Answer options with fraction scores

Support tables:
- `audit_log` - Full change tracking with JSON diff
- `reviews` - Review workflow states
- `usage` - Quiz insertion tracking
- `practice_attempts` / `practice_responses` - Practice mode tracking
- `practice_sessions` - Token-based secure sessions (64-char hex)
- `timed_attempts` - Timed mode with expiry
- `achievements` - User gamification data

### Case Status Flow

`draft` → `pending_review` → `in_review` → `approved` → `published` → `archived`

### Frontend Modules (amd/src/)

| Module | Purpose |
|--------|---------|
| `repository.js` | AJAX service calls wrapper |
| `case_editor.js` | Case editing UI |
| `question_editor.js` | Question/answer editing UI |
| `bulk_actions.js` | Multi-select bulk operations |
| `timer.js` | Countdown for timed practice mode |

### Templates (templates/)

Mustache templates following Moodle conventions. Main templates: `case_list`, `case_view`, `category_tree`, `question_form`, `answer_row`.

### Scheduled Tasks (classes/task/)

- `cleanup_abandoned_attempts` - Remove stale practice attempts
- `cleanup_practice_sessions` - Expire old session tokens
- `expire_timed_attempts` - Auto-submit expired timed attempts
- `cleanup_audit_logs` - Prune old audit entries (configurable retention)

### Events (classes/event/)

Fires Moodle events: `case_created`, `case_updated`, `case_deleted`, `case_published`, `practice_attempt_completed`, `timed_attempt_submitted`, `achievement_earned`, `rate_limit_exceeded`.

## Key Patterns

### Capability Checks
Always check capabilities at system context:
```php
require_capability('local/casospracticos:view', context_system::instance());
```

### Database Transactions
Use for multi-table operations:
```php
$transaction = $DB->start_delegated_transaction();
try {
    // operations...
    $transaction->allow_commit();
} catch (\Exception $e) {
    $transaction->rollback($e);
    throw $e;
}
```

### Rate Limiting
`rate_limiter` class handles API throttling. Configurable via settings for read (60/min) and write (30/min) operations.

### Testing Data Generator
Use `tests/generator/lib.php` for creating test fixtures:
```php
$generator = $this->getDataGenerator()->get_plugin_generator('local_casospracticos');
$category = $generator->create_category(['name' => 'Test']);
$case = $generator->create_case(['categoryid' => $category->id]);
```
