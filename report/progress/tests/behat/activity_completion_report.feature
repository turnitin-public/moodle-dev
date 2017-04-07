@report @report_progress
Feature: Teacher can view and override users' activity completion data via the progress report.
  In order to view and override a student's activity completion status
  As a teacher
  I need to view the course progress report and click the respective completion status icon

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 1 | C1        | topics | 1                |
    And the following "activities" exist:
      | activity   | name            | intro                         | course | idnumber    | section | completion | completionview |
      | assign     | my assignment   | Test assignment description   | C1     | assign1     | 0       | 1          | 0              |
      | assign     | my assignment 2 | Test assignment 2 description | C1     | assign2     | 0       | 2          | 1              |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | One | teacher1@example.com |
      | student1 | Student | One | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  # Course comprising one activity with auto completion (student must view it) and one with manual completion.
  # This confirms that after being completed by the student and overridden by the teacher, that both activities can still be
  # completed again via normal mechanisms.
  @javascript
  Scenario: Given the completion status has been overridden, when a student tries to complete it again, completion can still occur.
    # Student completes the activities, manual and automatic completion.
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And "Not completed: my assignment. Select to mark as complete." "icon" should exist in the "my assignment" "list_item"
    And "Not completed: my assignment 2" "icon" should exist in the "my assignment 2" "list_item"
    And I click on "Not completed: my assignment. Select to mark as complete." "icon"
    And "Completed: my assignment. Select to mark as not complete." "icon" should exist in the "my assignment" "list_item"
    And I click on "my assignment 2" "link"
    And I am on "Course 1" course homepage
    And "Completed: my assignment 2" "icon" should exist in the "my assignment 2" "list_item"
    And I log out
    # Teacher overrides the activity completion statuses to incomplete.
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Activity completion" node in "Course administration > Reports"
    And "Student One, my assignment: Completed" "icon" should exist in the "Student One" "table_row"
    And "Student One, my assignment 2: Completed" "icon" should exist in the "Student One" "table_row"
    And I click on "my assignment" "link" in the "Student One" "table_row"
    And I click on "Yes" "button"
    And "Student One, my assignment: Not completed (override by Teacher One)" "icon" should exist in the "Student One" "table_row"
    And I click on "my assignment 2" "link" in the "Student One" "table_row"
    And I click on "Yes" "button"
    And "Student One, my assignment 2: Not completed (override by Teacher One)" "icon" should exist in the "Student One" "table_row"
    And I log out
    # Student can now complete the activities again, via normal means.
    Then I log in as "student1"
    And I am on "Course 1" course homepage
    And "Not completed: my assignment (override by Teacher One). Select to mark as complete." "icon" should exist in the "my assignment" "list_item"
    And "Not completed: my assignment 2 (override by Teacher One)" "icon" should exist in the "my assignment 2" "list_item"
    And I click on "Not completed: my assignment (override by Teacher One). Select to mark as complete." "icon"
    And "Completed: my assignment. Select to mark as not complete." "icon" should exist in the "my assignment" "list_item"
    And I click on "my assignment 2" "link"
    And I am on "Course 1" course homepage
    And "Completed: my assignment 2" "icon" should exist in the "my assignment 2" "list_item"
    And I log out
    # And the activity completion report should show the same.
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Activity completion" node in "Course administration > Reports"
    And "Student One, my assignment: Completed" "icon" should exist in the "Student One" "table_row"
    And "Student One, my assignment 2: Completed" "icon" should exist in the "Student One" "table_row"