@local @local_casospracticos
Feature: Practical case management
  In order to manage practical cases for learning
  As a manager
  I need to create, edit and delete cases and their questions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | User     | manager1@example.com |
      | teacher1 | Teacher   | User     | teacher1@example.com |
      | student1 | Student   | User     | student1@example.com |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |
    And the following "local_casospracticos > categories" exist:
      | name           | description                    |
      | Test Category  | A category for testing         |
      | Sub Category   | A subcategory under test       |
    And I log in as "manager1"

  @javascript
  Scenario: Create a new practical case
    Given I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    When I click on "New case" "button"
    And I set the following fields to these values:
      | Case name   | Test Case 1                                    |
      | Category    | Test Category                                  |
      | Statement   | This is the statement for the test case.       |
      | Difficulty  | 3                                              |
      | Status      | Draft                                          |
    And I press "Save"
    Then I should see "Case created successfully"
    And I should see "Test Case 1"

  @javascript
  Scenario: Add a question to a case
    Given the following "local_casospracticos > cases" exist:
      | name        | category       | statement                      | status    |
      | Test Case 1 | Test Category  | Statement for testing          | draft     |
    When I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    And I click on "Test Case 1" "link"
    And I click on "New question" "button"
    And I set the following fields to these values:
      | Question type | Multiple choice                    |
      | Question text | What is the correct answer?        |
      | Default mark  | 1                                  |
    And I press "Save"
    Then I should see "Question created successfully"
    And I should see "What is the correct answer?"

  @javascript
  Scenario: Edit an existing case
    Given the following "local_casospracticos > cases" exist:
      | name        | category       | statement                      | status    |
      | Test Case 1 | Test Category  | Original statement             | draft     |
    When I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    And I click on "Test Case 1" "link"
    And I click on "Edit case" "button"
    And I set the following fields to these values:
      | Case name   | Updated Case Name                              |
      | Statement   | Updated statement text                         |
    And I press "Save"
    Then I should see "Case updated successfully"
    And I should see "Updated Case Name"

  @javascript
  Scenario: Delete a case
    Given the following "local_casospracticos > cases" exist:
      | name           | category       | statement                      | status    |
      | Case to Delete | Test Category  | This case will be deleted      | draft     |
    When I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    And I click on "Delete" "link" in the "Case to Delete" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should see "Case deleted successfully"
    And I should not see "Case to Delete"

  @javascript
  Scenario: Publish a case
    Given the following "local_casospracticos > cases" exist:
      | name        | category       | statement                      | status    |
      | Draft Case  | Test Category  | A case ready for publishing    | draft     |
    And the following "local_casospracticos > questions" exist:
      | case        | questiontext           | qtype         |
      | Draft Case  | Test question 1        | multichoice   |
    When I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    And I click on "Draft Case" "link"
    And I click on "Publish" "button"
    Then I should see "Case published"
    And I should see "Published" in the ".badge" "css_element"

  @javascript
  Scenario: Filter cases by status
    Given the following "local_casospracticos > cases" exist:
      | name           | category       | statement        | status    |
      | Draft Case 1   | Test Category  | Draft statement  | draft     |
      | Published Case | Test Category  | Pub statement    | published |
      | Archived Case  | Test Category  | Arch statement   | archived  |
    When I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    And I set the field "Status" to "Published"
    And I click on "Filter" "button"
    Then I should see "Published Case"
    And I should not see "Draft Case 1"
    And I should not see "Archived Case"

  @javascript
  Scenario: Student cannot create cases
    Given I log out
    And I log in as "student1"
    When I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    Then I should not see "New case"
    And I should not see "New category"
