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
$string['casospracticos:edit'] = 'Edit own practical cases';
$string['casospracticos:editall'] = 'Edit any practical case';
$string['casospracticos:delete'] = 'Delete own practical cases';
$string['casospracticos:deleteall'] = 'Delete any practical case';
$string['casospracticos:managecategories'] = 'Manage categories';
$string['casospracticos:export'] = 'Export practical cases';
$string['casospracticos:import'] = 'Import practical cases';
$string['casospracticos:insertquiz'] = 'Insert cases into quizzes';
$string['casospracticos:review'] = 'Review practical cases';
$string['casospracticos:viewaudit'] = 'View audit log';
$string['casospracticos:bulk'] = 'Perform bulk operations';

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
$string['status_pending_review'] = 'Pending review';
$string['status_in_review'] = 'In review';
$string['status_approved'] = 'Approved';
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
$string['qtype_matching'] = 'Matching';

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
$string['exporthelp'] = 'Select the cases to export or a complete category. If you do not select any specific cases and the category is set to "All", all cases will be exported.';

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
$string['print'] = 'Print';
$string['numquestions'] = '{$a} questions';
$string['created'] = 'Created';
$string['modified'] = 'Modified';
$string['createdby'] = 'Created by';
$string['filter'] = 'Filter';
$string['clear'] = 'Clear';
$string['selected'] = 'Selected';
$string['move'] = 'Move';
$string['publish'] = 'Publish';
$string['archive'] = 'Archive';

// Difficulty levels.
$string['difficulty1'] = 'Very easy';
$string['difficulty2'] = 'Easy';
$string['difficulty3'] = 'Medium';
$string['difficulty4'] = 'Hard';
$string['difficulty5'] = 'Very hard';

// Pagination and display.
$string['showingcases'] = 'Showing {$a->from} to {$a->to} of {$a->total} cases';
$string['showingitems'] = 'Showing {$a->from} to {$a->to} of {$a->total} items';
$string['searchcases'] = 'Search cases...';

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
$string['confirmdeleteselected'] = 'Are you sure you want to delete {$a} selected cases? This action cannot be undone.';
$string['confirmpublishselected'] = 'Are you sure you want to publish {$a} selected cases?';
$string['confirmarchiveselected'] = 'Are you sure you want to archive {$a} selected cases?';
$string['casesdeleted'] = '{$a} cases deleted successfully';
$string['casesmoved'] = '{$a} cases moved successfully';
$string['casespublished'] = '{$a} cases published successfully';
$string['casesarchived'] = '{$a} cases archived successfully';
$string['nocasesselected'] = 'No cases selected';
$string['selecttargetcategory'] = 'Select target category';

// Workflow.
$string['submitforreview'] = 'Submit for review';
$string['assignreviewer'] = 'Assign reviewer';
$string['approve'] = 'Approve';
$string['reject'] = 'Reject';
$string['requestrevision'] = 'Request revision';
$string['unarchive'] = 'Unarchive';
$string['casesubmittedreview'] = 'Case submitted for review';
$string['caseapproved'] = 'Case approved';
$string['caserejected'] = 'Case rejected';
$string['casepublished'] = 'Case published';
$string['casearchived'] = 'Case archived';
$string['invalidtransition'] = 'Invalid status transition';
$string['noquestionsforsubmit'] = 'Case must have at least one question before submitting for review';

// Review dashboard.
$string['reviewdashboard'] = 'Review dashboard';
$string['pendingreview'] = 'Pending review';
$string['myreviews'] = 'My reviews';
$string['mypendingreview'] = 'My pending reviews';
$string['approvedcases'] = 'Approved cases';
$string['allreviews'] = 'All reviews';
$string['reviews'] = 'Reviews';
$string['reviewer'] = 'Reviewer';
$string['caseswaitingassignment'] = 'Cases waiting for assignment';
$string['assigntome'] = 'Assign to me';
$string['reviewsubmitted'] = 'Review submitted successfully';
$string['reviewerassigned'] = 'Reviewer assigned successfully';
$string['noitemsfound'] = 'No items found';
$string['reviewstatus_pending'] = 'Pending';
$string['reviewstatus_approved'] = 'Approved';
$string['reviewstatus_rejected'] = 'Rejected';
$string['reviewstatus_revision'] = 'Revision requested';

// Audit log.
$string['auditlog'] = 'Audit log';
$string['noauditlogs'] = 'No audit log entries found';
$string['objecttype'] = 'Object type';
$string['objectid'] = 'Object ID';
$string['action'] = 'Action';
$string['changes'] = 'Changes';
$string['ipaddress'] = 'IP address';
$string['unknownuser'] = 'Unknown user';
$string['action_create'] = 'Create';
$string['action_update'] = 'Update';
$string['action_delete'] = 'Delete';
$string['action_publish'] = 'Publish';
$string['action_archive'] = 'Archive';
$string['action_submit_review'] = 'Submit for review';
$string['action_approve'] = 'Approve';
$string['action_reject'] = 'Reject';

// Events.
$string['eventcasecreated'] = 'Practical case created';
$string['eventcaseupdated'] = 'Practical case updated';
$string['eventcasedeleted'] = 'Practical case deleted';
$string['eventcasepublished'] = 'Practical case published';
$string['eventpracticeattemptcompleted'] = 'Practice attempt completed';
$string['eventratelimitexceeded'] = 'API rate limit exceeded';

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
$string['settings:workflow'] = 'Workflow settings';
$string['settings:enableworkflow'] = 'Enable approval workflow';
$string['settings:enableworkflow_desc'] = 'Require cases to be reviewed and approved before publishing';
$string['settings:importexport'] = 'Import/Export settings';
$string['settings:maximportsize'] = 'Maximum import file size';
$string['settings:maximportsize_desc'] = 'Maximum size in bytes for import files';
$string['settings:defaultexportformat'] = 'Default export format';
$string['settings:defaultexportformat_desc'] = 'Default format when exporting cases';
$string['settings:questiontypes'] = 'Question types';
$string['settings:questiontypes_desc'] = 'Configure which question types are available for cases';
$string['settings:allowedqtypes'] = 'Allowed question types';
$string['settings:allowedqtypes_desc'] = 'Select which question types can be used in practical cases';
$string['settings:display'] = 'Display settings';
$string['settings:casesperpage'] = 'Cases per page';
$string['settings:casesperpage_desc'] = 'Number of cases to display per page in the listing';
$string['settings:showquestioncount'] = 'Show question count';
$string['settings:showquestioncount_desc'] = 'Show the number of questions in the case listing';
$string['settings:showdifficulty'] = 'Show difficulty';
$string['settings:showdifficulty_desc'] = 'Show the difficulty level in the case listing';
$string['settings:notifications'] = 'Notifications';
$string['settings:notifyonpublish'] = 'Notify on publish';
$string['settings:notifyonpublish_desc'] = 'Send notifications when a case is published';
$string['settings:practicemode'] = 'Practice mode';
$string['settings:passthreshold'] = 'Pass threshold (%)';
$string['settings:passthreshold_desc'] = 'Percentage required to pass a practice attempt';
$string['settings:auditlogretention'] = 'Audit log retention (days)';
$string['settings:auditlogretention_desc'] = 'Number of days to keep audit log entries (old entries are cleaned up automatically)';
$string['settings:security'] = 'Security settings';
$string['settings:enableratelimiting'] = 'Enable rate limiting';
$string['settings:enableratelimiting_desc'] = 'Limit the number of API requests per user to prevent abuse (site admins are exempt)';
$string['settings:ratelimitread'] = 'Read operations limit';
$string['settings:ratelimitread_desc'] = 'Maximum read API requests per minute per user';
$string['settings:ratelimitwrite'] = 'Write operations limit';
$string['settings:ratelimitwrite_desc'] = 'Maximum write API requests per minute per user';

// Errors.
$string['error:categorynotfound'] = 'Category not found';
$string['error:casenotfound'] = 'Case not found';
$string['error:questionnotfound'] = 'Question not found';
$string['error:nopermission'] = 'You do not have permission to perform this action';
$string['error:invaliddata'] = 'Invalid data provided';
$string['error:nocases'] = 'No cases selected';
$string['error:ratelimitexceeded'] = 'Rate limit exceeded. Please wait a moment before trying again.';
$string['error:sessionexpired'] = 'Your practice session has expired. Please start a new attempt.';
$string['error:invalidsession'] = 'Invalid practice session';

// Privacy.
$string['privacy:metadata:local_cp_cases'] = 'Stores practical cases created by users';
$string['privacy:metadata:local_cp_cases:createdby'] = 'The ID of the user who created the case';
$string['privacy:metadata:local_cp_cases:timecreated'] = 'The time when the case was created';
$string['privacy:metadata:local_cp_cases:timemodified'] = 'The time when the case was last modified';
$string['privacy:metadata:local_cp_audit_log'] = 'Audit log of all actions performed';
$string['privacy:metadata:local_cp_audit_log:userid'] = 'The ID of the user who performed the action';
$string['privacy:metadata:local_cp_audit_log:action'] = 'The action performed by the user';
$string['privacy:metadata:local_cp_audit_log:ipaddress'] = 'The IP address of the user';
$string['privacy:metadata:local_cp_audit_log:timecreated'] = 'When the action was performed';
$string['privacy:metadata:local_cp_reviews'] = 'Case reviews for workflow';
$string['privacy:metadata:local_cp_reviews:reviewerid'] = 'The ID of the reviewer';
$string['privacy:metadata:local_cp_reviews:comments'] = 'Review comments from the reviewer';
$string['privacy:metadata:local_cp_reviews:status'] = 'The status of the review';
$string['privacy:metadata:local_cp_reviews:timecreated'] = 'When the review was created';
$string['privacy:metadata:local_cp_practice_attempts'] = 'Stores practice attempts made by users';
$string['privacy:metadata:local_cp_practice_attempts:userid'] = 'The ID of the user who made the attempt';
$string['privacy:metadata:local_cp_practice_attempts:score'] = 'The score obtained in the attempt';
$string['privacy:metadata:local_cp_practice_attempts:maxscore'] = 'The maximum possible score';
$string['privacy:metadata:local_cp_practice_attempts:percentage'] = 'The percentage score';
$string['privacy:metadata:local_cp_practice_attempts:timestarted'] = 'When the attempt was started';
$string['privacy:metadata:local_cp_practice_attempts:timefinished'] = 'When the attempt was finished';
$string['privacy:metadata:local_cp_practice_responses'] = 'Stores individual question responses in practice attempts';
$string['privacy:metadata:local_cp_practice_responses:response'] = 'The user response to the question';
$string['privacy:metadata:local_cp_practice_responses:score'] = 'The score for this response';
$string['privacy:metadata:local_cp_practice_responses:iscorrect'] = 'Whether the response was correct';

// Default category.
$string['defaultcategory'] = 'Imported cases';

// Practice mode.
$string['practice'] = 'Practice';
$string['practicecase'] = 'Practice this case';
$string['results'] = 'Results';
$string['yourscoreis'] = 'Your score: {$a->score} / {$a->max} ({$a->percentage}%)';
$string['retry'] = 'Try again';
$string['shufflequestions'] = 'Shuffle questions';
$string['correctansweris'] = 'Correct answer';

// Statistics.
$string['statistics'] = 'Statistics';
$string['totalviews'] = 'Total views';
$string['quizinsertions'] = 'Quiz insertions';
$string['practiceattempts'] = 'Practice attempts';
$string['averagescore'] = 'Average score';
$string['questionperformance'] = 'Question performance';
$string['attempts'] = 'Attempts';
$string['correctrate'] = 'Correct rate';
$string['avgpoints'] = 'Average points';
$string['usageinquizzes'] = 'Usage in quizzes';
$string['timesinserted'] = 'Times inserted';
$string['lastused'] = 'Last used';
$string['recentpracticeattempts'] = 'Recent practice attempts';
$string['timetaken'] = 'Time taken';
$string['scoredistribution'] = 'Score distribution';
$string['nostatisticsyet'] = 'No statistics available yet';
$string['notusedyet'] = 'This case has not been used in any quiz yet';
$string['nopracticeattempts'] = 'No practice attempts recorded yet';
$string['range020'] = '0-20%';
$string['range2140'] = '21-40%';
$string['range4160'] = '41-60%';
$string['range6180'] = '61-80%';
$string['range81100'] = '81-100%';

// Attempts.
$string['attempt'] = 'Attempt';
$string['myattempts'] = 'My attempts';
$string['viewmyattempts'] = 'View my attempts';
$string['reviewattempt'] = 'Review attempt';
$string['noattemptsyet'] = 'You have not made any attempts yet';
$string['startpractice'] = 'Start practicing';
$string['totalattempts'] = 'Total attempts';
$string['bestscore'] = 'Best score';
$string['passrate'] = 'Pass rate';
$string['yourrecentattempts'] = 'Your recent attempts';
$string['youranswer'] = 'Your answer';
$string['yourmark'] = 'Your mark';
$string['tryagain'] = 'Try again';
$string['practicenow'] = 'Practice now';
$string['started'] = 'Started';
$string['completed'] = 'Completed';
$string['correct'] = 'Correct';
$string['incorrect'] = 'Incorrect';
$string['review'] = 'Review';

// Help texts.
$string['cases_help'] = 'Hold Ctrl/Cmd to select multiple cases. If you select specific cases, only those will be exported. If no cases are selected, all cases from the chosen category will be exported.';

// Scheduled tasks.
$string['task:cleanupabandoned'] = 'Cleanup abandoned practice attempts';
$string['task:cleanupauditlogs'] = 'Cleanup old audit log entries';
$string['task:cleanuppracticesessions'] = 'Cleanup expired practice sessions';

// Achievements / Gamification.
$string['achievements'] = 'Achievements';
$string['gamificationdisabled'] = 'Gamification is currently disabled';
$string['uniquecases'] = 'Unique cases completed';
$string['perfectscores'] = 'Perfect scores';
$string['achievementsprogress'] = 'Achievements: {$a->earned} of {$a->total} earned';
$string['earned'] = 'Earned!';
$string['externalintegration'] = 'Integrated with {$a} for additional rewards';
$string['eventachievementearned'] = 'Achievement earned';
$string['settings:enablegamification'] = 'Enable gamification';
$string['settings:enablegamification_desc'] = 'Enable achievements and gamification features';

// Achievement types.
$string['achievement:first_attempt'] = 'First Steps';
$string['achievement:first_attempt_desc'] = 'Complete your first practice attempt';
$string['achievement:five_cases'] = 'Getting Started';
$string['achievement:five_cases_desc'] = 'Practice 5 different cases';
$string['achievement:ten_cases'] = 'Dedicated Learner';
$string['achievement:ten_cases_desc'] = 'Practice 10 different cases';
$string['achievement:twentyfive_cases'] = 'Case Master';
$string['achievement:twentyfive_cases_desc'] = 'Practice 25 different cases';
$string['achievement:perfect_score'] = 'Perfectionist';
$string['achievement:perfect_score_desc'] = 'Get a perfect score (100%)';
$string['achievement:five_perfect'] = 'Excellence';
$string['achievement:five_perfect_desc'] = 'Get 5 perfect scores';
$string['achievement:streak_10'] = 'On Fire!';
$string['achievement:streak_10_desc'] = 'Pass 10 cases in a row';
$string['achievement:week_streak'] = 'Consistent';
$string['achievement:week_streak_desc'] = 'Practice every day for a week';
$string['achievement:category_complete'] = 'Category Expert';
$string['achievement:category_complete_desc'] = 'Complete all cases in a category';
$string['achievement:high_achiever'] = 'High Achiever';
$string['achievement:high_achiever_desc'] = 'Maintain 90%+ average after 10 attempts';

// Message providers.
$string['messageprovider:casepublished'] = 'Practical case published';
$string['messageprovider:reviewassigned'] = 'Review assigned';
$string['messageprovider:reviewcompleted'] = 'Review completed';
$string['messageprovider:achievementearned'] = 'Achievement earned';

// Notification strings.
$string['notification:casepublished_subject'] = 'New practical case published: {$a}';
$string['notification:casepublished_body'] = 'A new practical case "{$a->casename}" has been published by {$a->author}.

View the case: {$a->url}';
$string['notification:casepublished_body_html'] = '<p>A new practical case "<strong>{$a->casename}</strong>" has been published by {$a->author}.</p><p><a href="{$a->url}">View the case</a></p>';
$string['notification:casepublished_small'] = 'New case: {$a}';

$string['notification:reviewassigned_subject'] = 'Review assigned: {$a}';
$string['notification:reviewassigned_body'] = 'You have been assigned to review the case "{$a->casename}" by {$a->author}.

View the case: {$a->url}';
$string['notification:reviewassigned_body_html'] = '<p>You have been assigned to review the case "<strong>{$a->casename}</strong>" by {$a->author}.</p><p><a href="{$a->url}">View the case</a></p>';
$string['notification:reviewassigned_small'] = 'Review assigned: {$a}';

$string['notification:reviewcompleted_subject'] = 'Review completed: {$a->casename} - {$a->status}';
$string['notification:reviewcompleted_body'] = 'Your case "{$a->casename}" has been reviewed by {$a->reviewer}.

Status: {$a->status}

Comments: {$a->comments}

View the case: {$a->url}';
$string['notification:reviewcompleted_body_html'] = '<p>Your case "<strong>{$a->casename}</strong>" has been reviewed by {$a->reviewer}.</p><p><strong>Status:</strong> {$a->status}</p><p><strong>Comments:</strong> {$a->comments}</p><p><a href="{$a->url}">View the case</a></p>';
$string['notification:reviewcompleted_small'] = 'Review: {$a->casename} - {$a->status}';

$string['notification:achievementearned_subject'] = 'Achievement unlocked: {$a}';
$string['notification:achievementearned_body'] = 'Congratulations! You have earned the achievement "{$a->achievement}".

{$a->description}

View your achievements: {$a->url}';
$string['notification:achievementearned_body_html'] = '<p>Congratulations! You have earned the achievement "<strong>{$a->achievement}</strong>".</p><p>{$a->description}</p><p><a href="{$a->url}">View your achievements</a></p>';
$string['notification:achievementearned_small'] = 'Achievement: {$a}';

// Review status strings.
$string['review_status_pending'] = 'Pending';
$string['review_status_approved'] = 'Approved';
$string['review_status_rejected'] = 'Rejected';
$string['review_status_revision_requested'] = 'Revision requested';

// Accessibility strings.
$string['caselist'] = 'List of practical cases';
$string['caseactions'] = 'Case actions';
$string['questionactions'] = 'Question actions';
$string['questionnumber'] = 'Question number';
$string['questionform'] = 'Question form';
$string['questiontype_help'] = 'Select the type of question to create';
$string['defaultmark_help'] = 'Points awarded for a fully correct answer';
$string['generalfeedback_help'] = 'Feedback shown after the question is answered';
$string['categoryoptions'] = 'Options for category';
$string['removeanswer'] = 'Remove this answer';
