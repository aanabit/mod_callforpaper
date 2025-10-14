@mod @mod_callforpaper @javascript @_file_upload
Feature: Users can import presets
  In order to use presets
  As a user
  I need to import and apply presets from zip files

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activities" exist:
      | activity | name                | intro | course | idnumber |
      | callforpaper     | Mountain landscapes | n     | C1     | callforpaper1    |

  Scenario: Teacher can import from preset page on an empty callforpaper
    Given I am on the "Mountain landscapes" "callforpaper activity" page logged in as teacher1
    And I follow "Presets"
    And I choose the "Import preset" item in the "Action" action menu
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    When I click on "Import preset and apply" "button"
    Then I should not see "Field mappings"
    And I should see "Image" in the "image" "table_row"

  Scenario: Teacher can import from preset page on a callforpaper with fields
    Given the following "mod_callforpaper > fields" exist:
      | callforpaper | type | name              | description              |
      | callforpaper1    | text | Test field name   | Test field description   |
    And I am on the "Mountain landscapes" "callforpaper activity" page logged in as teacher1
    And I follow "Presets"
    And I choose the "Import preset" item in the "Action" action menu
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    When I click on "Import preset and apply" "button"
    Then I should see "Field mappings"
    And I should see "image"
    And I should see "Create a new field" in the "image" "table_row"

  Scenario: Teacher can import from preset page on a callforpaper with entries
    And the following "mod_callforpaper > fields" exist:
      | callforpaper | type | name   | description              |
      | callforpaper1    | text | field1 | Test field description   |
    And the following "mod_callforpaper > templates" exist:
      | callforpaper | name            |
      | callforpaper1    | singletemplate  |
      | callforpaper1    | listtemplate    |
      | callforpaper1    | addtemplate     |
      | callforpaper1    | asearchtemplate |
      | callforpaper1    | rsstemplate     |
    And the following "mod_callforpaper > entries" exist:
      | callforpaper | field1          |
      | callforpaper1    | Student entry 1 |
    And I am on the "Mountain landscapes" "callforpaper activity" page logged in as teacher1
    And I follow "Presets"
    And I choose the "Import preset" item in the "Action" action menu
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    When I click on "Import preset and apply" "button"
    Then I should see "Field mappings"
    And I should see "image"
    And I should see "Create a new field" in the "image" "table_row"

  Scenario: Teacher can import from field page on a callforpaper with entries
    And the following "mod_callforpaper > fields" exist:
      | callforpaper | type | name   | description              |
      | callforpaper1    | text | field1 | Test field description   |
    And the following "mod_callforpaper > templates" exist:
      | callforpaper | name            |
      | callforpaper1    | singletemplate  |
      | callforpaper1    | listtemplate    |
      | callforpaper1    | addtemplate     |
      | callforpaper1    | asearchtemplate |
      | callforpaper1    | rsstemplate     |
    And the following "mod_callforpaper > entries" exist:
      | callforpaper | field1          |
      | callforpaper1    | Student entry 1 |
    And I am on the "Mountain landscapes" "callforpaper activity" page logged in as teacher1
    And I follow "Presets"
    And I choose the "Import preset" item in the "Action" action menu
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    When I click on "Import preset and apply" "button"
    Then I should see "Field mappings"
    And I should see "title"
    And I should see "Create a new field" in the "title" "table_row"
    # We map existing field to keep the entry callforpaper
    And I set the field "id_title" to "Map to field1"
    And I click on "Continue" "button"
    And I follow "Call for paper"
    And I should see "Student entry"

  Scenario: Teacher can import from zero state page on an empty callforpaper
    Given I am on the "Mountain landscapes" "callforpaper activity" page logged in as teacher1
    And I click on "Import a preset" "button"
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    When I click on "Import preset and apply" "button"
    Then I should not see "Field mappings"
    And I should see "Image" in the "image" "table_row"

  Scenario: Importing a preset could create new fields
    Given the following "mod_callforpaper > fields" exist:
      | callforpaper | type | name    |
      | callforpaper1    | text | title   |
    And I am on the "Mountain landscapes" "callforpaper activity" page logged in as teacher1
    And I follow "Fields"
    And I should see "title"
    And I should not see "Description"
    And I should not see "image"
    And I follow "Presets"
    And I choose the "Import preset" item in the "Action" action menu
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    When I click on "Import preset and apply" "button"
    And I click on "Continue" "button"
    And I should see "Preset applied"
    Then I should see "title"
    And I should see "description" in the "description" "table_row"
    And I should see "image" in the "image" "table_row"

  Scenario: Importing a preset could create map fields
    Given the following "mod_callforpaper > fields" exist:
      | callforpaper | type | name            |
      | callforpaper1    | text | oldtitle        |
    And I am on the "Mountain landscapes" "callforpaper activity" page logged in as teacher1
    And I follow "Fields"
    And I should see "oldtitle"
    And I should not see "Description"
    And I should not see "image"
    And I follow "Presets"
    And I choose the "Import preset" item in the "Action" action menu
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    When I click on "Import preset and apply" "button"
    # Let's map a field that is not mapped by default
    And I should see "Create a new field" in the "oldtitle" "table_row"
    And I set the field "id_title" to "Map to oldtitle"
    And I click on "Continue" "button"
    And I should see "Preset applied"
    Then I should not see "oldtitle"
    And I should see "title"
    And I should see "description" in the "description" "table_row"
    And I should see "image" in the "image" "table_row"

  Scenario: Importing same preset twice doesn't show mapping dialogue
    # Importing a preset on an empty callforpaper doesn't show the mapping dialogue, so we add a field for the callforpaper
    # not to be empty.
    Given the following "mod_callforpaper > fields" exist:
      | callforpaper | type | name    |
      | callforpaper1    | text | title   |
    And I am on the "Mountain landscapes" "callforpaper activity" page logged in as teacher1
    And I follow "Presets"
    And I choose the "Import preset" item in the "Action" action menu
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    When I click on "Import preset and apply" "button"
    And I should see "Field mappings"
    And I click on "Continue" "button"
    And I should see "Preset applied"
    And I follow "Presets"
    And I choose the "Import preset" item in the "Action" action menu
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    And I click on "Import preset and apply" "button"
    Then I should not see "Field mappings"
    And I should see "Preset applied"

  Scenario: Teacher can import from field page on a non-empty callforpaper and previous fields will be removed
    Given the following "mod_callforpaper > fields" exist:
      | callforpaper | type | name              | description              |
      | callforpaper1    | text | Test field name   | Test field description   |
    And I am on the "Mountain landscapes" "callforpaper activity" page logged in as teacher1
    And I follow "Presets"
    And I click on "Actions" "button"
    And I choose "Import preset" in the open action menu
    And I upload "mod/callforpaper/tests/fixtures/image_gallery_preset.zip" file to "Preset file" filemanager
    When I click on "Import preset and apply" "button"
    And I click on "Continue" "button"
    Then I should see "Preset applied."
    And I follow "Fields"
    And I should see "image"
    And I should see "title"
    And I should not see "Test field name"
