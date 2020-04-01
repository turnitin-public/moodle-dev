@core @core_user
Feature: First name and surname as well as additional names are displayed correctly for users and administrators
  In order to have the user names consistent
  As administrator or teacher when enrolling users
  I need to rely on the names being displayed as expected

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                | middlename | alternatename | firstnamephonetic | lastnamephonetic |
      | user1    | Grainne   | Beauchamp   | one@example.com      | Ann        | Jill          | Gronya            | Beecham          |
      | user2    | Niamh     | Cholmondely | two@example.com      | Jane       | Nina          | Nee               | Chumlee          |
      | teacher1 | Teacher   | 1           | teacher1@example.com |            |               |                   |                  |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I log in as "admin"
    And I navigate to "Users > Permissions > User policies" in site administration
    And the following config values are set as admin:
      | fullnamedisplay | firstnamephonetic,lastnamephonetic |
      | alternativefullnameformat | middlename, alternatename, firstname, lastname |

  @javascript
  Scenario: Enrol in a course when logged in as admin and see the alternative full name format
    When I am on "Course 1" course homepage
    And I navigate to course participants
    And I press "Enrol users"
    And I set the field "Select users" to "one@example.com"
    And I click on ".form-autocomplete-downarrow" "css_element" in the "Select users" "form_row"
    Then I should see "Ann, Jill, Grainne, Beauchamp"

  @javascript
  Scenario: Enrol in a course when logged in as teacher and see the alternative full name format
    Given I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    And I press "Enrol users"
    And I set the field "Select users" to "two@example.com"
    And I click on ".form-autocomplete-downarrow" "css_element" in the "Select users" "form_row"
    Then I should see "Jane, Nina, Niamh, Cholmondely"
