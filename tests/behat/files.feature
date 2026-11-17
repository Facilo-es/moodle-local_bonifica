@local @local_bonifica
Feature: Generate the FUNDAE completion file from the Files tab
  In order to upload a valid end file to the FUNDAE portal
  As a teacher who manages Bonifica
  I need to see when a group is ready, fix what blocks it in place and download the file

  Background:
    Given the following "courses" exist:
      | fullname           | shortname | format |
      | Ventas que cierran | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teresa    | Docente  | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "groups" exist:
      | name          | course | idnumber |
      | Grupo mañanas | C1     | G1       |

  Scenario: A group with complete, valid data is ready and its end file can be downloaded
    Given the course "C1" has a Bonifica action ready to file
    And I log in as "teacher1"
    When I am on the Bonifica "archivos" tab of course "C1"
    Then I should see "All set. The file meets the official FUNDAE"
    And I should not see "Before generating the file, fix this:"
    And the "Generate completion XML" "button" should be enabled
    And I should see "Final acceptance is up to FUNDAE."
    And I press "Generate completion XML"
    And I am on the Bonifica "archivos" tab of course "C1"
    And I should see "File generated. Upload it to the FUNDAE portal and mark it here once you have."
    And I press "Mark as uploaded"
    And I should see "Marked as uploaded."

  Scenario: A wrong document number blocks the file and can be fixed from the checklist
    Given the course "C1" has a Bonifica action with a participant with document "12345678A"
    And I log in as "teacher1"
    When I am on the Bonifica "archivos" tab of course "C1"
    Then I should see "Before generating the file, fix this:"
    And I should see "Participante 3"
    And the "Generate completion XML" "button" should be disabled
    And "Fix" "button" should exist
    And I set the field "Document number" to "12345678Z"
    And I press "Save"
    And I am on the Bonifica "archivos" tab of course "C1"
    And I should see "All set. The file meets the official FUNDAE"
    And the "Generate completion XML" "button" should be enabled

  Scenario: The groups list exports the ticked groups and states that FUNDAE has the last word
    Given the course "C1" has a Bonifica action ready to file
    And I log in as "teacher1"
    When I am on the Bonifica "grupos" tab of course "C1"
    Then I should see "Grupo mañanas"
    And "Generate completion XML for the ticked groups" "button" should exist
    And I should see "Final acceptance is up to FUNDAE."
