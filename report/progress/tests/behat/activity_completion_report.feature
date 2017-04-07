@report @report_progress
Feature: Teacher can view and override user's activity completion data via the progress report.
  In order view and override a student's completion status for an activity
  As a teacher
  I need to view the course progress report and click the respective completion status icon

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 1 | C1        | topics | 1                |
    And the following "activities" exist:
      | activity   | name            | intro                         | course | idnumber    | section | completion |
      | assign     | my assignment   | Test assignment description   | C1     | assign1     | 0       | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | Frist | teacher1@example.com |
      | student1 | Student | First | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  @javascript
  Scenario: Check the progress report for a given student.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Activity completion" node in "Course administration > Reports"
    And I pause

    And I set the field "instanceid" to "Test book name"
    And I set the field "roleid" to "Student"
    And I press "Go"
    Then I should see "Yes (1)"