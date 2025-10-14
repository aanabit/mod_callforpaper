@mod @mod_callforpaper
Feature: Testing overview integration in callforpaper activity
  In order to summarize the callforpaper activity
  As a user
  I need to be able to see the callforpaper activity overview

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | course          | C1                   |
      | activity        | callforpaper                 |
      | name            | Call for paper activity    |
      | intro           | description          |
      | idnumber        | callforpaper1                |
      | approval        | 1                    |
      | completion      | 1                    |
      | comments        | 1                    |
      | timeavailableto | ##1 Jan 2040 08:00## |
    And the following "activity" exists:
      | course          | C1                   |
      | activity        | callforpaper                 |
      | name            | Without comments     |
      | intro           | description          |
      | idnumber        | callforpaper2                |
      | approval        | 1                    |
      | completion      | 1                    |
      | comments        | 1                    |
      | timeavailableto | ##1 Jan 2040 08:00## |
    And the following "activity" exists:
      | course          | C1                   |
      | activity        | callforpaper                 |
      | name            | Empty callforpaper       |
      | intro           | empty callforpaper       |
      | idnumber        | callforpaper3                |
      | approval        | 0                    |
      | completion      | 0                    |
      | comments        | 0                    |
    And the following "mod_callforpaper > fields" exist:
      | callforpaper | type | name             | description                  |
      | callforpaper1    | text | Title field      | Title field description      |
      | callforpaper1    | text | Short text field | Short text field description |
      | callforpaper2    | text | Title field      | Title field description      |
      | callforpaper2    | text | Short text field | Short text field description |
    And the following "mod_callforpaper > templates" exist:
      | callforpaper | name            |
      | callforpaper1    | singletemplate  |
      | callforpaper1    | listtemplate    |
      | callforpaper1    | addtemplate     |
      | callforpaper1    | asearchtemplate |
      | callforpaper1    | rsstemplate     |
      | callforpaper2    | singletemplate  |
      | callforpaper2    | listtemplate    |
      | callforpaper2    | addtemplate     |
      | callforpaper2    | asearchtemplate |
      | callforpaper2    | rsstemplate     |
    And the following "mod_callforpaper > entries" exist:
      | callforpaper | user     | Title field           | Short text field | approved |
      | callforpaper1    | student1 | Student entry         | Approved         | 1        |
      | callforpaper1    | student1 | Student second entry  | Pending          | 0        |
      | callforpaper1    | teacher1 | Teacher entry         | Approved         | 1        |
      | callforpaper2    | teacher1 | Entry no comments     | Approved         | 1        |

  Scenario: The callforpaper activity overview report should generate log events
    Given I am on the "Course 1" "course > activities > callforpaper" page logged in as "teacher1"
    When I am on the "Course 1" "course" page logged in as "teacher1"
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I click on "Get these logs" "button"
    Then I should see "Course activities overview page viewed"
    And I should see "viewed the instance list for the module 'callforpaper'"

  @javascript
  Scenario: Students can see relevant columns in the callforpaper activity overview
    # Add a comment to test the values.
    Given I am on the "Call for paper activity" "callforpaper activity" page logged in as student1
    And I select "Single view" from the "jump" singleselect
    And I click on "Comments (0)" "link"
    And I set the following fields to these values:
      | Comment        | Commenting the entry |
    And I click on "Save comment" "link"
    When I am on the "Course 1" "course > activities > callforpaper" page
    # Check columns.
    Then I should see "Name" in the "callforpaper_overview_collapsible" "region"
    And I should see "Status" in the "callforpaper_overview_collapsible" "region"
    # Check column values.
    And the following should exist in the "Table listing all Call for paper activities" table:
      | Name              | Due date       | Total entries | My entries | Comments  |
      | Call for paper activity | 1 January 2040 | 2             | 2          | 1         |
      | Without comments  | 1 January 2040 | 1             | 0          | 0         |
      | Empty callforpaper    | -              | 0             | 0          | -         |

  @javascript
  Scenario: Teachers can see relevant columns in the callforpaper activity overview
    # Add a comment to test the values.
    Given I am on the "Call for paper activity" "callforpaper activity" page logged in as teacher1
    And I select "Single view" from the "jump" singleselect
    And I click on "Comments (0)" "link"
    And I set the following fields to these values:
      | Comment        | Commenting the entry |
    And I click on "Save comment" "link"
    When I am on the "Course 1" "course > activities > callforpaper" page
    # Check columns.
    And I should not see "My entries" in the "callforpaper_overview_collapsible" "region"
    And I should not see "Total entries" in the "callforpaper_overview_collapsible" "region"
    # Check column values.
    Then the following should exist in the "Table listing all Call for paper activities" table:
      | Name              | Due date       | Entries | Comments | Actions     |
      | Call for paper activity | 1 January 2040 | 3       | 1        | Approve (1) |
      | Without comments  | 1 January 2040 | 1       | 0        | View        |
      | Empty callforpaper    | -              | 0       | -        | View        |
    # Check the Approve link.
    And I click on "Approve" "link" in the "callforpaper_overview_collapsible" "region"
    And I should see "Pending approval"

  Scenario: The callforpaper activity index redirect to the activities overview
    When I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Activities" block
    And I click on "Call for paper" "link" in the "Activities" "block"
    Then I should see "An overview of all activities in the course"
    And I should see "Name" in the "callforpaper_overview_collapsible" "region"
    And I should see "Due date" in the "callforpaper_overview_collapsible" "region"
    And I should see "Actions" in the "callforpaper_overview_collapsible" "region"
