# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-01-11

### Added
- **Backup/Restore**: Full support for Moodle course backup and restore
  - Categories, cases, questions, and answers are included in backups
  - File attachments are properly backed up and restored
  - User mapping for case creators
- **Event System**: Integration with Moodle events
  - `case_created` - Triggered when a new case is created
  - `case_updated` - Triggered when a case is modified
  - `case_deleted` - Triggered when a case is removed
  - `case_published` - Triggered when a case status changes to published
- **Caching Layer**: Performance optimization with MUC
  - Category tree caching (1 hour TTL)
  - Case data caching (30 minutes TTL)
  - Question data caching (30 minutes TTL)
  - Case counts caching (30 minutes TTL)
  - Session cache for recent cases
- **Search Integration**: Global search support
  - Cases indexed in Moodle's search engine
  - Search by case name, statement, category
  - Access control respects capabilities
- **Event Observers**: Automatic cleanup on user/course deletion
  - Anonymizes cases when user is deleted
  - Removes cases and categories when course is deleted
- **Admin Settings**: Comprehensive configuration options
  - Enable/disable quiz integration
  - Enable/disable search indexing
  - Default difficulty level
  - Maximum import file size
  - Allowed question types
  - Cases per page
  - Display options
  - Notification settings
- **Plugin Icon**: SVG icon for the plugin
- **Documentation**: README.md with complete documentation

### Fixed
- Privacy provider: Added missing `transform` class import
- Quiz integration: Completed stub method implementation

### Changed
- Version bumped to 0.2.0
- Maturity changed from ALPHA to BETA

## [0.1.0] - 2026-01-11

### Added
- Initial release
- Core case management functionality
  - Create, edit, delete practical cases
  - Rich HTML statements with media support
  - Status management (draft, published, archived)
  - Difficulty levels (1-5)
- Question management
  - Multiple choice questions (single/multiple answers)
  - True/False questions
  - Short answer questions
  - Drag-and-drop reordering
- Category system
  - Hierarchical category structure
  - Category management interface
- Quiz integration
  - Insert cases into Moodle quizzes
  - Random question selection
  - Include statement as description
- Import/Export
  - XML format support
  - JSON format support
  - Bulk import/export
- Web services API
  - RESTful endpoints for all operations
  - External API for integrations
- Mustache templates
  - Case list view
  - Case detail view
  - Question form
  - Answer row component
  - Category tree
- JavaScript AMD modules
  - Case editor with inline editing
  - Question editor modal
  - AJAX repository
- PHPUnit tests
  - Category manager tests
  - Case manager tests
  - Question manager tests
  - Test data generator
- Privacy API compliance
  - GDPR-compliant data handling
  - User data export
  - User data anonymization
- Multilingual support
  - English language pack
  - Spanish language pack
