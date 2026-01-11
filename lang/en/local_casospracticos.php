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
 * English language strings for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Practical Cases';
$string['casospracticos'] = 'Practical Cases';

// Capabilities.
$string['casospracticos:view'] = 'View practical cases';
$string['casospracticos:create'] = 'Create practical cases';
$string['casospracticos:edit'] = 'Edit practical cases';
$string['casospracticos:delete'] = 'Delete practical cases';
$string['casospracticos:managecategories'] = 'Manage categories';
$string['casospracticos:export'] = 'Export practical cases';
$string['casospracticos:import'] = 'Import practical cases';
$string['casospracticos:insertquiz'] = 'Insert cases into quizzes';

// Categories.
$string['categories'] = 'Categories';
$string['category'] = 'Category';
$string['newcategory'] = 'New category';
$string['editcategory'] = 'Edit category';
$string['deletecategory'] = 'Delete category';
$string['categoryname'] = 'Category name';
$string['categorydescription'] = 'Description';
$string['parentcategory'] = 'Parent category';
$string['nocategories'] = 'No categories yet';
$string['toplevel'] = 'Top level';
$string['categorycreated'] = 'Category created successfully';
$string['categoryupdated'] = 'Category updated successfully';
$string['categorydeleted'] = 'Category deleted successfully';
$string['categoryhaschildren'] = 'Cannot delete category with subcategories';
$string['categoryhascases'] = 'Cannot delete category with cases';

// Cases.
$string['cases'] = 'Cases';
$string['case'] = 'Case';
$string['newcase'] = 'New case';
$string['editcase'] = 'Edit case';
$string['deletecase'] = 'Delete case';
$string['viewcase'] = 'View case';
$string['casename'] = 'Case name';
$string['casestatement'] = 'Statement';
$string['casestatement_help'] = 'The long description or problem statement that students will read before answering the questions.';
$string['nocases'] = 'No cases in this category';
$string['casecreated'] = 'Case created successfully';
$string['caseupdated'] = 'Case updated successfully';
$string['casedeleted'] = 'Case deleted successfully';
$string['confirmdeletecase'] = 'Are you sure you want to delete this case? This will also delete all its questions.';
$string['difficulty'] = 'Difficulty';
$string['difficulty_help'] = 'Difficulty level from 1 (easy) to 5 (hard)';
$string['tags'] = 'Tags';

// Status.
$string['status'] = 'Status';
$string['status_draft'] = 'Draft';
$string['status_published'] = 'Published';
$string['status_archived'] = 'Archived';

// Questions.
$string['questions'] = 'Questions';
$string['question'] = 'Question';
$string['newquestion'] = 'New question';
$string['editquestion'] = 'Edit question';
$string['deletequestion'] = 'Delete question';
$string['questiontext'] = 'Question text';
$string['questiontype'] = 'Question type';
$string['noquestions'] = 'No questions in this case';
$string['questioncreated'] = 'Question created successfully';
$string['questionupdated'] = 'Question updated successfully';
$string['questiondeleted'] = 'Question deleted successfully';
$string['defaultmark'] = 'Default points';
$string['generalfeedback'] = 'General feedback';
$string['shuffleanswers'] = 'Shuffle answers';
$string['singleanswer'] = 'Single answer';
$string['multipleanswers'] = 'Multiple answers';

// Question types.
$string['qtype_multichoice'] = 'Multiple choice';
$string['qtype_truefalse'] = 'True/False';
$string['qtype_shortanswer'] = 'Short answer';

// Answers.
$string['answers'] = 'Answers';
$string['answer'] = 'Answer';
$string['newanswer'] = 'Add answer';
$string['answertext'] = 'Answer text';
$string['fraction'] = 'Grade';
$string['fraction_help'] = '1.0 = correct answer, 0 = wrong answer. Use values in between for partial credit.';
$string['feedback'] = 'Feedback';
$string['correctanswer'] = 'Correct';
$string['incorrectanswer'] = 'Incorrect';

// Import/Export.
$string['export'] = 'Export';
$string['import'] = 'Import';
$string['exportcases'] = 'Export cases';
$string['importcases'] = 'Import cases';
$string['exportformat'] = 'Export format';
$string['importfile'] = 'Import file';
$string['exportsuccessful'] = 'Export completed successfully';
$string['importsuccessful'] = 'Import completed successfully. {$a->cases} cases and {$a->questions} questions imported.';
$string['importerror'] = 'Error during import: {$a}';

// Quiz integration.
$string['insertintoquiz'] = 'Insert into quiz';
$string['selectquiz'] = 'Select quiz';
$string['randomquestions'] = 'Random questions';
$string['randomquestions_help'] = 'Number of random questions to include from this case. Leave at 0 to include all questions.';
$string['includestatement'] = 'Include statement';
$string['includestatement_help'] = 'If enabled, the case statement will be inserted as a description before the questions.';
$string['insertsuccessful'] = 'Case inserted into quiz successfully';
$string['inserterror'] = 'Error inserting case into quiz: {$a}';

// Navigation and general.
$string['managecases'] = 'Manage practical cases';
$string['backtocases'] = 'Back to cases';
$string['backtocategories'] = 'Back to categories';
$string['actions'] = 'Actions';
$string['preview'] = 'Preview';
$string['numquestions'] = '{$a} questions';
$string['created'] = 'Created';
$string['modified'] = 'Modified';
$string['createdby'] = 'Created by';

// Errors.
$string['error:categorynotfound'] = 'Category not found';
$string['error:casenotfound'] = 'Case not found';
$string['error:questionnotfound'] = 'Question not found';
$string['error:nopermission'] = 'You do not have permission to perform this action';
$string['error:invaliddata'] = 'Invalid data provided';

// Privacy.
$string['privacy:metadata:local_cp_cases'] = 'Stores practical cases created by users';
$string['privacy:metadata:local_cp_cases:createdby'] = 'The ID of the user who created the case';
$string['privacy:metadata:local_cp_cases:timecreated'] = 'The time when the case was created';
$string['privacy:metadata:local_cp_cases:timemodified'] = 'The time when the case was last modified';
<?php
// Additional strings for events, settings, search - English.

// Events.
$string['eventcasecreated'] = 'Practical case created';
$string['eventcaseupdated'] = 'Practical case updated';
$string['eventcasedeleted'] = 'Practical case deleted';
$string['eventcasepublished'] = 'Practical case published';

// Search.
$string['search:case'] = 'Practical cases';

// Settings.
$string['settings:general'] = 'General settings';
$string['settings:enablequizintegration'] = 'Enable quiz integration';
$string['settings:enablequizintegration_desc'] = 'Allow inserting practical cases into Moodle quizzes';
$string['settings:enablesearch'] = 'Enable search indexing';
$string['settings:enablesearch_desc'] = 'Index practical cases in Moodle global search';
$string['settings:defaultdifficulty'] = 'Default difficulty';
$string['settings:defaultdifficulty_desc'] = 'Default difficulty level for new cases';
$string['settings:importexport'] = 'Import/Export';
$string['settings:maximportsize'] = 'Maximum import file size';
$string['settings:maximportsize_desc'] = 'Maximum file size in bytes for importing cases';
$string['settings:defaultexportformat'] = 'Default export format';
$string['settings:defaultexportformat_desc'] = 'Default format when exporting cases';
$string['settings:questiontypes'] = 'Question types';
$string['settings:questiontypes_desc'] = 'Configure which question types are available';
$string['settings:allowedqtypes'] = 'Allowed question types';
$string['settings:allowedqtypes_desc'] = 'Select which question types can be used in cases';
$string['settings:display'] = 'Display settings';
$string['settings:casesperpage'] = 'Cases per page';
$string['settings:casesperpage_desc'] = 'Number of cases to display per page in the list view';
$string['settings:showquestioncount'] = 'Show question count';
$string['settings:showquestioncount_desc'] = 'Display the number of questions for each case in the list';
$string['settings:showdifficulty'] = 'Show difficulty';
$string['settings:showdifficulty_desc'] = 'Display the difficulty level in the case list';
$string['settings:notifications'] = 'Notifications';
$string['settings:notifyonpublish'] = 'Notify on publish';
$string['settings:notifyonpublish_desc'] = 'Send notification to administrators when a case is published';

// Difficulty levels.
$string['difficulty1'] = 'Very easy';
$string['difficulty2'] = 'Easy';
$string['difficulty3'] = 'Medium';
$string['difficulty4'] = 'Hard';
$string['difficulty5'] = 'Very hard';

// Question types.
$string['qtype_multichoice'] = 'Multiple choice';
$string['qtype_truefalse'] = 'True/False';
$string['qtype_shortanswer'] = 'Short answer';
$string['qtype_matching'] = 'Matching';

// Statuses.
$string['status_draft'] = 'Draft';
$string['status_published'] = 'Published';
$string['status_archived'] = 'Archived';

// Default category.
$string['defaultcategory'] = 'Imported cases';

// Pagination.
$string['page'] = 'Page';
$string['of'] = 'of';
$string['showingcases'] = 'Showing {$a->start} to {$a->end} of {$a->total} cases';
$string['nocasesfound'] = 'No cases found matching your criteria';

// Search and filter.
$string['searchcases'] = 'Search cases';
$string['filterbycat'] = 'Filter by category';
$string['filterbystatus'] = 'Filter by status';
$string['filterbydifficulty'] = 'Filter by difficulty';
$string['allcategories'] = 'All categories';
$string['allstatuses'] = 'All statuses';
$string['alldifficulties'] = 'All difficulties';
$string['clearsearch'] = 'Clear search';
$string['advancedfilters'] = 'Advanced filters';

// Bulk operations.
$string['bulkoperations'] = 'Bulk operations';
$string['selectall'] = 'Select all';
$string['deselectall'] = 'Deselect all';
$string['withselected'] = 'With selected...';
$string['bulkdelete'] = 'Delete selected';
$string['bulkmove'] = 'Move to category';
$string['bulkpublish'] = 'Publish selected';
$string['bulkarchive'] = 'Archive selected';
$string['bulkexport'] = 'Export selected';
$string['confirmdeleteselected'] = 'Are you sure you want to delete the selected cases? This action cannot be undone.';
$string['casesdeleted'] = '{$a} cases deleted successfully';
$string['casesmoved'] = '{$a} cases moved successfully';
$string['casespublished'] = '{$a} cases published successfully';
$string['casesarchived'] = '{$a} cases archived successfully';
