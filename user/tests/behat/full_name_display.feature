@core @core_user
Feature: First name and surname as well as additional names are displayed correctly for users and administrators
  In order to have my user menu consistent with everywhere else my name is shown
  As any user
  I need to rely on my name being displayed as expected

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
      | user1    | C1     | student        |
      | user2    | C1     | student        |
    And I log in as "admin"
    And I navigate to "Users > Permissions > User policies" in site administration
    And the following config values are set as admin:
      | fullnamedisplay | firstnamephonetic,lastnamephonetic |
      | alternativefullnameformat | middlename, alternatename, firstname, lastname |

  Scenario: See my name in the course profile displayed in the fullnamedisplay format
    When I log out
    And I log in as "user1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    And I click on "Gronya,Beecham" "link" in the "Gronya,Beecham" "table_row"
    Then I should see "Gronya,Beecham" in the "region-main" "region"

  Scenario: As admin, in the course profile, still see the name in alternativefullname format when fullnamedisplay is changed
    Given I log out
    And I log in as "admin"
    And I navigate to "Users > Permissions > User policies" in site administration
    And the following config values are set as admin:
      | fullnamedisplay | alternatename and lastname |
    And I am on "Course 1" course homepage
    And I navigate to course participants
    And I should not see "Gronya,Beecham"
    Then I should see "Ann, Jill, Grainne, Beauchamp"

  Scenario: See my name in my profile
    When I log out
    And I log in as "user1"
    And I follow "Dashboard" in the user menu
    Then I should see "Gronya,Beecham" in the ".usermenu" "css_element"

  Scenario: See my name in my profile logged in as another user
    When I log out
    And I log in as "admin"
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    And I follow "Jane, Nina, Niamh, Cholmondely"
    And I follow "Log in as"
    Then I should see "You are logged in as Nee,Chumlee"

  Scenario: See my name, as admin, in the user's site profile
    When I navigate to "Users > Accounts > Browse list of users" in site administration
    And I follow "Ann, Jill, Grainne, Beauchamp"
    Then I should see "Gronya,Beecham" in the ".page-header-headings" "css_element"

  Scenario: See my name, as another user, in the user's course profile
    When I log out
    And I log in as "user2"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    And I click on "Gronya,Beecham" "link" in the "Gronya,Beecham" "table_row"
    Then I should see "Gronya,Beecham" in the "region-main" "region"