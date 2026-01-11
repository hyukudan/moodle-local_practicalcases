# Practical Cases Plugin for Moodle

[![Moodle Plugin](https://img.shields.io/badge/Moodle-5.1+-orange.svg)](https://moodle.org)
[![PHP](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v3-green.svg)](https://www.gnu.org/licenses/gpl-3.0)

A Moodle local plugin for managing practical cases with associated questions. Perfect for law schools, medical education, business case studies, and any scenario-based learning.

## Features

- **Case Management**: Create, edit, and organize practical cases with rich HTML statements
- **Question Bank**: Each case has its own set of questions (multiple choice, true/false, short answer)
- **Hierarchical Categories**: Organize cases in a tree structure of categories
- **Quiz Integration**: Insert cases directly into Moodle quizzes with random question selection
- **Import/Export**: XML and JSON format support for sharing cases
- **Search Integration**: Cases are indexed in Moodle's global search
- **Caching**: Optimized performance with MUC (Moodle Universal Cache)
- **Backup/Restore**: Full support for Moodle course backups
- **Privacy API**: GDPR compliant with full privacy provider implementation
- **Events System**: Integration with Moodle's event system for logging and triggers

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

### Version 0.1.0 (2026-01-11)
- Initial release
- Core case and question management
- Quiz integration
- Import/Export functionality
- Search integration
- Backup/Restore support
- Event system
- Caching layer
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
