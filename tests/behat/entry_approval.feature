@mod @mod_callforpaper
Feature: User can see the entry approval status on the single view and list view
  in the default template.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name               | intro          | course | idnumber | approval |
      | callforpaper     | Test callforpaper name | Call for paper intro | C1     | callforpaper1    | 1        |
    And the following "mod_callforpaper > fields" exist:
      | callforpaper | type | name   | description              |
      | callforpaper1    | text | field1 | Test field description   |
      | callforpaper1    | text | field2 | Test field 2 description |
    And the following "mod_callforpaper > templates" exist:
      | callforpaper | name            |
      | callforpaper1    | singletemplate  |
      | callforpaper1    | listtemplate    |
      | callforpaper1    | addtemplate     |
      | callforpaper1    | asearchtemplate |
      | callforpaper1    | rsstemplate     |
    And the following "mod_callforpaper > entries" exist:
      | callforpaper | user     | field1          | field2                 |
      | callforpaper1    | student1 | Student entry 1 | Some student content 1 |
      | callforpaper1    | teacher1 | Teacher entry 1 | Some teacher content 1 |

  @javascript
  Scenario Outline: The approval status is displayed in the single view and list view next to the action menu
    # List view.
    Given I am on the "Test callforpaper name" "callforpaper activity" page logged in as <user>
    Then I should see "Pending approval" in the "region-main" "region"
    # Single view.
    And I select "Single view" from the "jump" singleselect
    And I should see "Pending approval" in the "region-main" "region"
    And I click on "2" "link" in the ".pagination" "css_element"
    And I should not see "Pending approval" in the "region-main" "region"
    And I should not see "Approved" in the "region-main" "region"
    Examples:
      | user     |
      | student1 |
      | teacher1 |
