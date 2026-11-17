@local @local_bonifica
Feature: Prepare a course's FUNDAE action in Bonifica
  In order to file bonified training without typing everything twice
  As a teacher who manages Bonifica
  I need to fill in the action from the course and see what still blocks the FUNDAE file

  Background:
    Given the following "courses" exist:
      | fullname           | shortname | format |
      | Ventas que cierran | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teresa    | Docente  | teacher1@example.com |
      | student1 | Sergio    | Alumno   | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: A teacher starts Bonifica, fills in the action and sees the checklist with ways to fix each problem
    Given I am on the "C1" "Course" page logged in as "teacher1"
    When I navigate to "Bonifica" in current page administration
    Then I should see "Prepare your FUNDAE files without typing them twice."
    And I press "Get started"
    And I should see "Action number (FUNDAE)"
    And I set the following fields to these values:
      | Action number (FUNDAE) | 00217              |
      | Action name            | Ventas que cierran |
      | Delivery mode          | Online             |
      | Online hours           | 20                 |
    And I press "Save changes"
    And I should see "Changes saved."
    And the following fields match these values:
      | Action number (FUNDAE) | 00217 |
      | Online hours           | 20    |
    And I follow "Files"
    And I should see "Before generating the file, fix this:"
    And I should see "Turns on when nothing is left to fix"
    And the "Generate completion XML" "button" should be disabled
    And "Go to Groups" "link" should exist
    And I follow "Go to Groups"
    And I should see "FUNDAE number"

  Scenario: The action form refuses an action number that is not 5 digits at most
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Bonifica" in current page administration
    And I press "Get started"
    When I set the field "Action number (FUNDAE)" to "ABC1234"
    And I press "Save changes"
    Then I should see "Digits only, 5 at most."

  Scenario: A student cannot reach the Bonifica page
    Given the course "C1" has a Bonifica action ready to file
    And I log in as "student1"
    Then I should not be able to open the Bonifica page of course "C1"
    And I am on "Ventas que cierran" course homepage
    And "Bonifica" "link" should not exist
