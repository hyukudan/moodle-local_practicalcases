@local @local_casospracticos
Feature: Practice mode for practical cases
  In order to learn and practice
  As a student
  I need to practice cases and see my results

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | User     | student1@example.com |
    And the following "local_casospracticos > categories" exist:
      | name           |
      | Test Category  |
    And the following "local_casospracticos > cases" exist:
      | name        | category       | statement                      | status    |
      | Test Case   | Test Category  | Practice this case             | published |
    And the following "local_casospracticos > questions" exist:
      | case      | questiontext           | qtype         |
      | Test Case | What is 2 + 2?         | multichoice   |
      | Test Case | Is the sky blue?       | truefalse     |
    And the following "local_casospracticos > answers" exist:
      | question       | answer      | fraction |
      | What is 2 + 2? | 4           | 1        |
      | What is 2 + 2? | 5           | 0        |
      | What is 2 + 2? | 3           | 0        |

  @javascript
  Scenario: Student can practice a published case
    Given I log in as "student1"
    When I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    And I click on "Test Case" "link"
    And I click on "Practice" "button"
    Then I should see "Practice this case"
    And I should see "What is 2 + 2?"
    And I should see "Is the sky blue?"

  @javascript
  Scenario: Student can submit practice answers and see results
    Given I log in as "student1"
    And I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    And I click on "Test Case" "link"
    And I click on "Practice" "button"
    When I click on "4" "radio"
    And I click on "True" "radio"
    And I press "Submit"
    Then I should see "Your score"
    And I should see "100%"

  @javascript
  Scenario: Student can view their attempts history
    Given I log in as "student1"
    And I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    And I click on "Test Case" "link"
    When I click on "View my attempts" "button"
    Then I should see "My attempts"

  @javascript
  Scenario: Student can retry a practice
    Given I log in as "student1"
    And I navigate to "Plugins > Local plugins > Manage practical cases" in site administration
    And I click on "Test Case" "link"
    And I click on "Practice" "button"
    And I click on "4" "radio"
    And I click on "True" "radio"
    And I press "Submit"
    When I click on "Try again" "button"
    Then I should see "What is 2 + 2?"
