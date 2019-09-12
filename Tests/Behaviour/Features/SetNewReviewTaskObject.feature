@fixtures
Feature: Schedule task

  As a user of Bitzer I want to be able to set a new object for a review task

  Background:
    Given I have no content dimensions
    And I have the following NodeTypes configuration:
    """
    'unstructured': []
    'Neos.Neos:Document':
      properties:
        title:
          type: string
        uriPathSegment:
          type: string
    'Sitegeist.Bitzer:Testing.Document':
      superTypes:
        'Neos.Neos:Document': true

    """
    And I have the following nodes:
      | Identifier             | Path                                           | Node Type                         | Properties                                                                    | Workspace |
      | sites                  | /sites                                         | unstructured                      | {}                                                                            | live      |
      | sity-mc-siteface       | /sites/sity-mc-siteface                        | Neos.Neos:Document                | {}                                                                            | live      |
      | nody-mc-nodeface       | /sites/sity-mc-siteface/sity-mc-siteface       | Sitegeist.Bitzer:Testing.Document | {"title":"Nody McNodeface", "uriPathSegment":"nody-mc-nodeface"}              | live      |
      | sir-david-nodenborough | /sites/sity-mc-siteface/sir-david-nodenborough | Sitegeist.Bitzer:Testing.Document | {"title":"Sir David Nodenborough", "uriPathSegment":"sir-david-nodenborough"} | live      |
    And I have the following sites:
      | nodeName         | name | siteResourcesPackageKey |
      | sity-mc-siteface | Site | Sitegeist.TestSite      |
    And I have the following additional agents:
    """
    'Sitegeist.Bitzer:TestingAgent':
      parentRoles: ['Sitegeist.Bitzer:Agent']
    'Sitegeist.Bitzer:TestingAdministrator':
      parentRoles: ['Sitegeist.Bitzer:Administrator']
    """
    When the command ScheduleTask is executed with payload:
      | Key            | Value                                                                                            |
      | taskIdentifier | "tasky-mc-taskface"                                                                              |
      | taskClassName  | "Sitegeist\\Bitzer\\Review\\Domain\\Task\\Review\\ReviewTask"                                    |
      | scheduledTime  | "2020-01-02T00:00:00+00:00"                                                                      |
      | agent          | "Sitegeist.Bitzer:TestingAgent"                                                                  |
      | object         | {"nodeAggregateIdentifier":"nody-mc-nodeface", "workspaceName":"live", "dimensionSpacePoint":{}} |
      | properties     | {"description":"task description"}                                                               |

  Scenario: Try to remove the object from a review task
    When the command SetNewTaskObject is executed with payload and exceptions are caught:
      | Key            | Value               |
      | taskIdentifier | "tasky-mc-taskface" |
    Then the last command should have thrown an exception of type "ObjectIsInvalid"

  Scenario: Try to remove the object from a review task using a constraint check result
    Given exceptions are collected in a constraint check result
    When the command SetNewTaskObject is executed with payload:
      | Key            | Value               |
      | taskIdentifier | "tasky-mc-taskface" |
    Then I expect the constraint check result to contain an exception of type "ObjectIsInvalid" at path "object"
    And I expect the task "tasky-mc-taskface" to exist
    And I expect this task to be about '{"nodeAggregateIdentifier":"nody-mc-nodeface", "workspaceName":"live", "dimensionSpacePoint":{}}'

  Scenario: Set a new valid object to an existing task
    Given I am authenticated as existing user "me"
    When the command SetNewTaskObject is executed with payload:
      | Key            | Value                                                                                                  |
      | taskIdentifier | "tasky-mc-taskface"                                                                                    |
      | object         | {"nodeAggregateIdentifier":"sir-david-nodenborough", "workspaceName":"live", "dimensionSpacePoint":{}} |
    Then I expect the task "tasky-mc-taskface" to exist
    And I expect this task to be about '{"nodeAggregateIdentifier":"sir-david-nodenborough", "workspaceName":"live", "dimensionSpacePoint":{}}'
    And I expect this task to have the target "http://localhost/sir-david-nodenborough@user-me.html"
