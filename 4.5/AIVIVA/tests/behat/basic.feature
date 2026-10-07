@mod @mod_aiviva
Feature: Basic use of the AI Viva activity
  In order to assess students with an AI viva
  As a teacher
  I need students to reach the activity and to review their attempts

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name          | idnumber | max_attempts |
      | aiviva   | C1     | Thesis viva   | viva1    | 2            |

  Scenario: A student must accept the privacy notice before starting
    When I am on the "Thesis viva" "aiviva activity" page logged in as student1
    Then I should see "Privacy Notice"
    And I should see "OpenAI"
    And I should see "Attempt 0 of 2"
    And I should not see "Drag and drop your PDF here"
    When I set the field "consent" to "1"
    And I press "I'm ready to begin"
    Then I should see "Drag and drop your PDF here"
    And I should see "Attempt 1 of 2"
    And I should not see "Privacy Notice"

  Scenario: A teacher sees the submissions page instead of the student steps
    When I am on the "Thesis viva" "aiviva activity" page logged in as teacher1
    Then I should see "View submissions"
    And I should not see "Privacy Notice"
    When I follow "View submissions"
    Then I should see "No submissions yet"

  Scenario: A closed activity cannot be started
    Given the following "activities" exist:
      | activity | course | name        | idnumber | timeclose  |
      | aiviva   | C1     | Closed viva | viva2    | 1000000000 |
    When I am on the "Closed viva" "aiviva activity" page logged in as student1
    Then I should see "This activity is closed"
    And I should not see "Privacy Notice"

  Scenario: A teacher creates an activity from the settings form
    Given I log in as "teacher1"
    When I add a aiviva activity to course "Course 1" section "1" and I fill the form with:
      | Activity name | Capstone viva |
    And I am on the "Capstone viva" "aiviva activity" page
    Then I should see "View submissions"
    When I navigate to "Settings" in current page administration
    Then the field "Hold grades for teacher review" matches value "1"

  Scenario: A teacher gives a student extra attempts with an override
    Given I am on the "Thesis viva" "aiviva activity" page logged in as teacher1
    And I navigate to "User/Group Overrides" in current page administration
    When I follow "Add override"
    And I set the following fields to these values:
      | User             | Sam Student (student1@example.com) |
      | Maximum attempts | 5                                  |
    And I press "Save changes"
    Then I should see "Override saved"
    And I should see "Sam Student"
    When I follow "Edit"
    And I set the field "Maximum attempts" to "3"
    And I press "Save changes"
    Then I should see "Override saved"
    When I am on the "Thesis viva" "aiviva activity" page logged in as student1
    Then I should see "Attempt 0 of 3"

  Scenario: The override user list only shows the identity fields the site allows
    Given the following config values are set as admin:
      | showuseridentity | |
    And I am on the "Thesis viva" "aiviva activity" page logged in as teacher1
    And I navigate to "User/Group Overrides" in current page administration
    When I follow "Add override"
    Then the "User" select box should contain "Sam Student"
    And I should not see "student1@example.com"

  Scenario: An administrator sees the licence status on the settings page
    Given I log in as "admin"
    When I navigate to "Plugins > Activity modules > AI Viva" in site administration
    Then I should see "Evaluation period"
    And I should see "Primary OpenAI API Key"
