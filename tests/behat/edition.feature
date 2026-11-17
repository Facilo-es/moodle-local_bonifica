@local @local_bonifica
Feature: The free edition offers Pro instead of running it
  In order to know what Bonifica Pro adds
  As a manager using the free edition
  I need to see the Pro offer where Pro features would be, and never the Pro features themselves

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
    And the course "C1" has a Bonifica action ready to file

  Scenario: The Files tab shows the Pro offer and no Pro file buttons
    Given I log in as "teacher1"
    When I am on the Bonifica "archivos" tab of course "C1"
    Then I should see "Bonifica Pro"
    And I should see "Coming soon"
    And "Generate training action XML" "button" should not exist
    And "Generate group start XML" "button" should not exist

  Scenario: The Action and Participants tabs keep Pro functions out of the free edition
    Given I log in as "teacher1"
    When I am on the Bonifica "accion" tab of course "C1"
    Then I should not see "Learning checks"
    And I am on the Bonifica "participantes" tab of course "C1"
    And I should see "All 2 participants are ready"
    And "Fill in from enrolments" "button" should not exist

  Scenario: The summary dashboard shows the Pro offer to a manager
    Given I log in as "admin"
    When I visit "/local/bonifica/dashboard.php"
    Then I should see "Bonifica Pro"
    And I should see "Coming soon"
