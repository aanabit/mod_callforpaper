@mod @mod_callforpaper
Feature: Group callforpaper activity
  In order to create a callforpaper with my group
  As a student
  I need to add and view entries for the groups I am a member of

  Background:
    And the following "courses" exist:
      | fullname      | shortname |
      | Test Course 1 | C1        |
    And the following "groups" exist:
      | name    | course | idnumber | participation |
      | Group 1 | C1     | G1       | 1             |
      | Group 2 | C1     | G2       | 1             |
      | Group 3 | C1     | G3       | 0             |
    And the following "users" exist:
      | username | firstname    | lastname | email                |
      | teacher1 | TeacherG1    | 1        | teacher1@example.com |
      | teacher2 | TeacherGNone | 2        | teacher2@example.com |
      | teacher3 | TeacherGNone | 3        | teacher3@example.com |
      | user1    | User1G1      | 1        | user1@example.com    |
      | user2    | User2G2      | 2        | user2@example.com    |
      | user3    | User3None    | 3        | user3@example.com    |
      | user4    | User4NPgroup | 4        | user4@example.com    |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | teacher3 | C1     | teacher        |
      | user1    | C1     | student        |
      | user2    | C1     | student        |
      | user3    | C1     | student        |
      | user4    | C1     | student        |
    And the following "group members" exist:
      | user     | group |
      | teacher1 | G1    |
      | user1    | G1    |
      | user2    | G2    |
      | user4    | G3    |
    And the following "activities" exist:
      | activity | name          | intro                     | course | idnumber | groupmode |
      | callforpaper     | Separate callforpaper | callforpaper with separate groups | C1     | callforpaper1    | 1         |
      | callforpaper     | Visible callforpaper  | callforpaper with visible groups  | C1     | callforpaper2    | 2         |
    And the following "mod_callforpaper > fields" exist:
      | callforpaper | type      | name         |
      | callforpaper1    | shorttext | separatetext |
      | callforpaper2    | shorttext | visibletext  |
    And the following "mod_callforpaper > entries" exist:
      | callforpaper | user  | group | separatetext       |
      | callforpaper1    | user1 | G1    | I am user 1        |
      | callforpaper1    | user2 | G2    | I am user 2        |
    And the following "mod_callforpaper > entries" exist:
      | callforpaper | user  | separatetext       |
      | callforpaper1    | user3 | I am user 3        |
    And the following "mod_callforpaper > entries" exist:
      | callforpaper | user  | group | visibletext        |
      | callforpaper2    | user1 | G1    | I am user 1        |
      | callforpaper2    | user2 | G2    | I am user 2        |
    And the following "mod_callforpaper > entries" exist:
      | callforpaper | user  | visibletext        |
      | callforpaper2    | user3 | I am user 3        |
      | callforpaper2    | user4 | I am user 4        |

  Scenario Outline: Users should see their own participation groups in "separate groups" mode, and all
  participation groups in "visible groups" mode.
    Given I am on the "<callforpaper>" "callforpaper activity" page logged in as "<user>"
    Then I <all> "All participants"
    And I <G1> "Group 1"
    And I <user1> "I am user 1"
    And I <G2> "Group 2"
    And I <user2> "I am user 2"
    # All users should see entries with no group.
    And I should see "I am user 3"
    # No-one should see non-participation groups.
    And I should not see "Group 3"

    Examples:
      | callforpaper  | user     | all            | G1             | G2             | user1          | user2          |
      | callforpaper1 | teacher1 | should see     | should see     | should see     | should see     | should see     |
      | callforpaper1 | teacher2 | should see     | should see     | should see     | should see     | should see     |
      | callforpaper1 | teacher3 | should see     | should not see | should not see | should not see | should not see |
      | callforpaper1 | user1    | should not see | should see     | should not see | should see     | should not see |
      | callforpaper1 | user2    | should not see | should not see | should see     | should not see | should see     |
      | callforpaper1 | user3    | should see     | should not see | should not see | should not see | should not see |
      | callforpaper1 | user4    | should see     | should not see | should not see | should not see | should not see |
      | callforpaper2 | teacher1 | should see     | should see     | should see     | should see     | should see     |
      | callforpaper2 | teacher2 | should see     | should see     | should see     | should see     | should see     |
      | callforpaper2 | teacher3 | should see     | should see     | should see     | should see     | should not see |
      | callforpaper2 | user1    | should see     | should see     | should see     | should see     | should not see |
      | callforpaper2 | user2    | should see     | should see     | should see     | should not see | should see     |
      | callforpaper2 | user3    | should see     | should see     | should see     | should see     | should not see |
      | callforpaper2 | user4    | should see     | should see     | should see     | should see     | should not see |

  Scenario Outline: When viewing a callforpaper in visible groups mode,
  a user should only have the "Add entry" button for their own participation groups
    Given I am on the "callforpaper2" "callforpaper activity" page logged in as "<user>"
    And I select "<mygroup>" from the "group" singleselect
    And I should see "Add entry"
    When I select "<othergroup>" from the "group" singleselect
    Then I should not see "Add entry"

    Examples:
      | user  | mygroup          | othergroup |
      | user1 | Group 1          | Group 2    |
      | user2 | Group 2          | Group 1    |
      | user1 | All participants | Group 2    |
      | user2 | All participants | Group 1    |

  Scenario Outline: Users in no groups or non-participation groups should not be able to add entries
    Given I am on the "<callforpaper>" "callforpaper activity" page logged in as "<user>"
    Then I should not see "Add entry"

    Examples:
      | callforpaper  | user     |
      | callforpaper1 | user3    |
      | callforpaper1 | user4    |
      | callforpaper2 | user3    |
      | callforpaper2 | user4    |
