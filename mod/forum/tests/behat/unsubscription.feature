@mod @mod_forum
Feature: A user can control their own subscription preferences for a discussion
  In order to receive notifications for things I am interested in
  As a user
  I need to choose my discussion subscriptions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student   | One      | student.one@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And I log in as "admin"

  # Due to caching issue in discussion_subscriptions feature file
  # moving this scenario to a separate file.
  Scenario: An automatic forum prompts a user to subscribe to a discussion when posting unless they have already chosen not to subscribe
    Given the following "activity" exists:
      | activity       | forum                  |
      | course         | C1                     |
      | idnumber       | forum1                 |
      | name           | Test forum name        |
      | intro          | Test forum description |
      | type           | general                |
      | forcesubscribe | 2                      |
    And I am on "Course 1" course homepage
    And I add a new discussion to "Test forum name" forum with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I add a new discussion to "Test forum name" forum with:
      | Subject | Test post subject two |
      | Message | Test post message two |
    And I am on the "Test forum name" "forum activity" page
    And I navigate to "Settings" in current page administration
    And I log out
    When I am on the "Test forum name" "forum activity" page logged in as student1
    And I should see "Unsubscribe from forum"
    And I reply "Test post subject one" post from "Test forum name" forum with:
      | Subject | Reply 1 to discussion 1 |
      | Message | Discussion contents 1, second message |
      | Discussion subscription | 1 |
    And I reply "Test post subject two" post from "Test forum name" forum with:
      | Subject | Reply 1 to discussion 1 |
      | Message | Discussion contents 1, second message |
      | Discussion subscription | 0 |
    And I am on the "Test forum name" "forum activity" page
    Then "Unsubscribe from this discussion" "checkbox" should exist in the "Test post subject one" "table_row"
    And "Subscribe to this discussion" "checkbox" should exist in the "Test post subject two" "table_row"
