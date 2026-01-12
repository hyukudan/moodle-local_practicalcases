# Practical Cases Plugin for Moodle

[![Moodle Plugin](https://img.shields.io/badge/Moodle-5.1+-orange.svg)](https://moodle.org)
[![PHP](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v3-green.svg)](https://www.gnu.org/licenses/gpl-3.0)

A Moodle local plugin for managing practical cases with associated questions. Perfect for law schools, medical education, business case studies, and any scenario-based learning.

## Features

### Core
- **Case Management**: Create, edit, and organize practical cases with rich HTML statements
- **Question Bank**: Each case has its own set of questions (multiple choice, true/false, short answer)
- **Hierarchical Categories**: Organize cases in a tree structure of categories
- **Import/Export**: XML and JSON format support for sharing cases

### Learning
- **Practice Mode**: Self-study mode with immediate feedback and attempt tracking
- **Quiz Integration**: Insert cases directly into Moodle quizzes with random question selection
- **Gamification**: Optional achievements system (integrates with block_xp)
- **Statistics**: Detailed analytics per case and question performance

### Workflow
- **Review System**: Submit cases for review, approval workflow
- **Audit Logging**: Track all changes with full audit trail
- **Bulk Operations**: Mass delete, move, publish, archive operations

### Technical
- **Search Integration**: Cases are indexed in Moodle's global search
- **Notifications**: Configurable notifications for publishing and reviews
- **Caching**: Optimized performance with MUC (Moodle Universal Cache)
- **Backup/Restore**: Full support for Moodle course backups (including user attempts)
- **Privacy API**: GDPR compliant with full privacy provider implementation
- **Events System**: Integration with Moodle's event system for logging and triggers
- **Web Services**: Complete REST API for external integrations

## Requirements

- Moodle 4.4 or higher (5.1+ recommended)
- PHP 8.0 or higher

## Installation

### From ZIP file

1. Download the latest release from GitHub
2. Extract to `/local/casospracticos` in your Moodle installation
3. Visit Site Administration > Notifications to complete installation

### From Git

```bash
cd /path/to/moodle/local
git clone https://github.com/yourusername/moodle-local_casospracticos.git casospracticos
```

Then visit Site Administration > Notifications.

## Configuration

Navigate to **Site Administration > Plugins > Local plugins > Practical Cases** to configure:

- Enable/disable quiz integration
- Enable/disable search indexing
- Default difficulty level
- Maximum import file size
- Allowed question types
- Cases per page
- Display options

## Usage

### Creating a Category

1. Go to **Site Administration > Plugins > Local plugins > Manage Practical Cases**
2. Click "New Category"
3. Enter name and description
4. Save

### Creating a Case

1. Navigate to the cases list
2. Click "New Case"
3. Fill in:
   - **Name**: Short title for the case
   - **Category**: Select from your categories
   - **Statement**: The full case description (supports HTML, images, etc.)
   - **Difficulty**: 1-5 scale
   - **Status**: Draft, Published, or Archived

### Adding Questions

1. Open a case
2. Click "Add Question"
3. Choose question type:
   - **Multiple Choice**: One or more correct answers
   - **True/False**: Binary choice
   - **Short Answer**: Text input matching
4. Enter question text and answers
5. Set correct answer(s) with percentage marks

### Inserting into Quiz

1. Open the case you want to use
2. Click "Insert into Quiz"
3. Select target quiz
4. Configure options:
   - Number of random questions (0 = all)
   - Include statement as description
   - Shuffle questions
5. Click "Insert"

### Import/Export

**Export:**
1. Select cases to export
2. Choose format (XML or JSON)
3. Download file

**Import:**
1. Go to Import page
2. Upload XML/JSON file
3. Select target category
4. Review and confirm

## Capabilities

| Capability | Description | Default Roles |
|------------|-------------|---------------|
| `local/casospracticos:view` | View practical cases | All authenticated users |
| `local/casospracticos:create` | Create new cases | Manager, Course creator |
| `local/casospracticos:edit` | Edit existing cases | Manager, Course creator |
| `local/casospracticos:delete` | Delete cases | Manager |
| `local/casospracticos:managecategories` | Manage categories | Manager |
| `local/casospracticos:export` | Export cases | Manager, Course creator |
| `local/casospracticos:import` | Import cases | Manager |
| `local/casospracticos:insertquiz` | Insert cases into quizzes | Manager, Course creator, Teacher |

## Web Services

The plugin provides a complete REST API for external integrations:

| Service | Description |
|---------|-------------|
| `local_casospracticos_get_categories` | Get all categories |
| `local_casospracticos_get_cases` | Get cases (with filters) |
| `local_casospracticos_get_case` | Get single case with questions |
| `local_casospracticos_create_case` | Create a new case |
| `local_casospracticos_update_case` | Update existing case |
| `local_casospracticos_delete_case` | Delete a case |
| `local_casospracticos_get_questions` | Get questions for a case |
| `local_casospracticos_create_question` | Add question to case |
| `local_casospracticos_update_question` | Update a question |
| `local_casospracticos_delete_question` | Delete a question |
| `local_casospracticos_reorder_questions` | Change question order |
| `local_casospracticos_insert_into_quiz` | Insert case into quiz |

## Events

The plugin triggers the following events for integration:

- `\local_casospracticos\event\case_created`
- `\local_casospracticos\event\case_updated`
- `\local_casospracticos\event\case_deleted`
- `\local_casospracticos\event\case_published`

## Database Schema

```
local_cp_categories     - Case categories (hierarchical)
local_cp_cases          - Practical cases with statements
local_cp_questions      - Questions belonging to cases
local_cp_answers        - Answer options for questions
```

## Development

### Running Tests

```bash
cd /path/to/moodle
vendor/bin/phpunit --testsuite local_casospracticos_testsuite
```

### Code Style

Follow Moodle coding style:
```bash
php admin/cli/check_coding_style.php --path=local/casospracticos
```

## Changelog

### Version 1.0.1 (2026-01-11) - Security Hardening
- **Security**: Added CSRF protection (sesskey) to direct export endpoint
- **Security**: Added case existence validation before export
- **Security**: Strengthened authorization checks in review_attempt.php
- **Security**: Fixed PARAM_RAW usage - now validates fraction values in whitelist
- **Quality**: Added return type hints to case_manager methods

### Version 1.0.0 (2026-01-11) - Stable Release
- **Quality**: Database transactions for atomic operations in bulk_manager and case_manager
- **Accessibility**: Added comprehensive ARIA labels and screen reader support
- **Accessibility**: Improved keyboard navigation in all templates
- **Accessibility**: Added proper semantic roles (article, navigation, toolbar, etc.)
- **Testing**: Added Behat tests for case management and practice mode workflows
- **Maturity**: Changed to MATURITY_STABLE

### Version 0.9.0 (2026-01-11) - Pre-release Candidate
- **Security**: Fixed XXE vulnerability in XML import
- **Security**: Added comprehensive input validation in importer
- **Performance**: Fixed N+1 query in get_categories API
- **Performance**: Optimized batch loading for answers in question_manager
- **Performance**: Added missing database indexes for better query performance
- **New**: Notifications system for case publishing and reviews
- **New**: Practice mode with user attempts tracking
- **New**: Achievements/gamification system (optional integration with block_xp)
- **New**: Statistics and analytics for cases
- **Improved**: Backup/restore now includes practice attempts
- **Improved**: Better error handling with warnings in import

### Version 0.7.0 (2026-01-11)
- Added achievements manager with 10 achievement types
- Added practice attempt tracking and statistics
- Added scheduled task for cleanup of abandoned attempts
- Optimized filter_manager queries (correlated subquery fix)
- Added caching for filter options
- Full GDPR compliance for all user data tables

### Version 0.6.0 (2026-01-11)
- Added practice mode for self-study
- Added review workflow (pending review, in review, approved, rejected)
- Added audit logging for all changes
- Added usage tracking for analytics
- Added statistics page per case

### Version 0.5.0 (2026-01-11)
- Added bulk operations (delete, move, publish, archive)
- Added advanced filtering (tags, date range, difficulty)
- Added pagination for large case lists
- Improved UI with Bootstrap 5 components

### Version 0.4.0 (2026-01-10)
- Added web services API for external integrations
- Added events system integration
- Added search indexing for global search
- Added backup/restore support

### Version 0.3.0 (2026-01-10)
- Quiz integration with random question selection
- Statement as description option
- Import/Export in XML and JSON formats

### Version 0.2.0 (2026-01-10)
- Hierarchical categories
- Question types: Multiple choice, True/False, Short answer
- Answer feedback support
- Difficulty levels

### Version 0.1.0 (2026-01-10)
- Initial release
- Core case and question management
- Basic category organization
- Privacy API compliance

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests
5. Submit a pull request

## License

This plugin is licensed under the [GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.html).

## Support

- [GitHub Issues](https://github.com/yourusername/moodle-local_casospracticos/issues)
- [Moodle Forums](https://moodle.org/mod/forum/view.php?id=44)

## Credits

- **Author**: Sergio C.
- **Organization**: Prepara Oposiciones

---

Made with dedication for educators worldwide.
