@local @local_casospracticos
Feature: Practising practical cases with an entitlement
  In order to learn from real cases
  As a student entitled to the practical-case product
  I need to practise cases and read their answer keys and reasoned solutions

  # Access to the bank is decided by entitlement — an active enrolment in the
  # product course — never by a capability. Scenarios must therefore grant that
  # entitlement explicitly and enter through the learner bank (real_bank.php).
  # index.php is the back-office and refuses ordinary learners on purpose.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | User     | student1@example.com |
      | student2 | Nonpaying | User     | student2@example.com |
    And "student1" has "FULL" access to the practical-case bank
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
  Scenario: A student with FULL access can practise a published case
    Given I log in as "student1"
    When I am on the "local_casospracticos > Bank" page
    And I click on "Test Case" "link"
    And I click on "Practise" "link"
    Then I should see "Practice this case"
    And I should see "What is 2 + 2?"
    And I should see "Is the sky blue?"

  @javascript
  Scenario: A student with FULL access can submit answers and see results
    Given I log in as "student1"
    And I am on the "local_casospracticos > Bank" page
    And I click on "Test Case" "link"
    And I click on "Practise" "link"
    When I click on "4" "radio"
    And I click on "True" "radio"
    And I press "Submit"
    Then I should see "Your score"
    And I should see "100%"

  @javascript
  Scenario: A student with FULL access can open their attempts history
    Given I log in as "student1"
    And I am on the "local_casospracticos > Bank" page
    And I click on "Test Case" "link"
    When I click on "View my attempts" "link"
    Then I should see "My attempts"

  @javascript
  Scenario: A student with FULL access can retry a completed practice
    Given I log in as "student1"
    And I am on the "local_casospracticos > Bank" page
    And I click on "Test Case" "link"
    And I click on "Practise" "link"
    And I click on "4" "radio"
    And I click on "True" "radio"
    And I press "Submit"
    When I click on "Try again" "link"
    Then I should see "What is 2 + 2?"

  # This is the policy the product sells: paying for the course buys the answer
  # key and the reasoned solution. It used to assert the opposite, and the code
  # honoured that, so no paying student ever saw a solution.
  @javascript
  Scenario: A student with FULL access sees the answer key and the reasoned solution
    Given the following "local_casospracticos > answers" exist:
      | question       | answer                  | fraction |
      | What is 2 + 2? | SECRET CASE ANSWER KEY  | 1        |
    And the practical-case question "What is 2 + 2?" has reasoning "SECRET REASONED SOLUTION"
    And I log in as "student1"
    When I am on the "local_casospracticos > Bank" page
    And I click on "Test Case" "link"
    Then I should see "What is 2 + 2?"
    And I should see "SECRET CASE ANSWER KEY"
    And I should see "SECRET REASONED SOLUTION"

  @javascript
  Scenario: A student without an entitlement gets no case content at all
    Given the following "local_casospracticos > answers" exist:
      | question       | answer                  | fraction |
      | What is 2 + 2? | SECRET CASE ANSWER KEY  | 1        |
    And "student2" has "NONE" access to the practical-case bank
    And I log in as "student2"
    When I am on the "local_casospracticos > Bank" page
    Then I should not see "SECRET CASE ANSWER KEY"
    And I should not see "What is 2 + 2?"

  # The trial is a sample: statement yes, questions and keys no.
  @javascript
  Scenario: A trial student sees the statement but never the questions or keys
    Given the following "local_casospracticos > answers" exist:
      | question       | answer                  | fraction |
      | What is 2 + 2? | SECRET CASE ANSWER KEY  | 1        |
    And the practical-case trial policy is "statement"
    And "student2" has "STATEMENT" access to the practical-case bank
    And I log in as "student2"
    When I am on the "local_casospracticos > Bank" page
    And I click on "Test Case" "link"
    Then I should see "Practice this case"
    And I should not see "What is 2 + 2?"
    And I should not see "SECRET CASE ANSWER KEY"

  @javascript
  Scenario: An essay from a student with FULL access stays pending manual grading
    Given the following "local_casospracticos > cases" exist:
      | name       | category      | statement              | status    |
      | Essay Case | Test Category | Explain your reasoning | published |
    And the following "local_casospracticos > questions" exist:
      | case       | questiontext         | qtype |
      | Essay Case | Give a reasoned view | essay |
    And I log in as "student1"
    When I am on the "local_casospracticos > Bank" page
    And I click on "Essay Case" "link"
    And I click on "Practise" "link"
    And I set the field "Your answer" to "My reasoned response"
    And I press "Submit"
    Then I should see "pending manual grading"
    And I should not see "0%"

  # Blocked questions are pulled from circulation by editorial: absent from the
  # case view and from practice. (That the bank card does not COUNT them either
  # is asserted in the unit tests, not here: the count sits in bare markup.)
  @javascript
  Scenario: Blocked questions are absent from case view and from practice
    Given the following "local_casospracticos > cases" exist:
      | name         | category      | statement         | status    |
      | Blocked Case | Test Category | Safe statement    | published |
    And the following "local_casospracticos > questions" exist:
      | case         | questiontext              | qtype       | feedbackstatus |
      | Blocked Case | UNSAFE BLOCKED QUESTION   | multichoice | blocked        |
      | Blocked Case | Safe visible question     | multichoice | verified       |
    And I log in as "student1"
    When I am on the "local_casospracticos > Bank" page
    And I click on "Blocked Case" "link"
    Then I should see "Safe visible question"
    And I should not see "UNSAFE BLOCKED QUESTION"
    When I click on "Practise" "link"
    Then I should see "Safe visible question"
    And I should not see "UNSAFE BLOCKED QUESTION"

  # The breadcrumb used to point every learner page at the back-office, so the
  # error was one click away from every case.
  @javascript
  Scenario: A learner is never sent to the back-office from a case
    Given I log in as "student1"
    And I am on the "local_casospracticos > Bank" page
    When I click on "Test Case" "link"
    Then "//a[contains(@href,'local/casospracticos/index.php')]" "xpath_element" should not exist
