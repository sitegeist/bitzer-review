@fixtures
Feature: Schedule task

  As a user of Bitzer I want to be able to schedule a review task

  Background:
    Given I have the following content dimensions:
      | Identifier | Default |
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

  Scenario: Try to schedule a review task without an object
    When the command ScheduleTask is executed with payload and exceptions are caught:
      | Key            | Value                                                         |
      | taskIdentifier | "tasky-mc-taskface"                                           |
      | taskClassName  | "Sitegeist\\Bitzer\\Review\\Domain\\Task\\Review\\ReviewTask" |
      | scheduledTime  | "2020-01-01T00:00:00+00:00"                                   |
      | agent          | "Sitegeist.Bitzer:TestingAgent"                               |
      | properties     | {"description":"task description"}                            |
    Then the last command should have thrown an exception of type "ObjectIsUndefined"

  Scenario: Try to schedule a review task without an object using a constraint check result
    Given exceptions are collected in a constraint check result
    When the command ScheduleTask is executed with payload:
      | Key            | Value                                                         |
      | taskIdentifier | "tasky-mc-taskface"                                           |
      | taskClassName  | "Sitegeist\\Bitzer\\Review\\Domain\\Task\\Review\\ReviewTask" |
      | scheduledTime  | "2020-01-01T00:00:00+00:00"                                   |
      | agent          | "Sitegeist.Bitzer:TestingAgent"                               |
      | properties     | {"description":"task description"}                            |
    Then I expect the constraint check result to contain an exception of type "ObjectIsUndefined" at path "object"
    And I expect the task "tasky-mc-taskface" not to exist

  Scenario: Try to schedule a review task with a manual target
    When the command ScheduleTask is executed with payload and exceptions are caught:
      | Key            | Value                                                                                            |
      | taskIdentifier | "tasky-mc-taskface"                                                                              |
      | taskClassName  | "Sitegeist\\Bitzer\\Review\\Domain\\Task\\Review\\ReviewTask"                                    |
      | scheduledTime  | "2020-01-01T00:00:00+00:00"                                                                      |
      | agent          | "Sitegeist.Bitzer:TestingAgent"                                                                  |
      | object         | {"nodeAggregateIdentifier":"nody-mc-nodeface", "workspaceName":"live", "dimensionSpacePoint":{}} |
      | target         | "https://www.neos.io"                                                                            |
      | properties     | {"description":"task description"}                                                               |
    Then the last command should have thrown an exception of type "TargetIsInvalid"

  Scenario: Try to schedule a review task with a manual target using a constraint check result
    Given exceptions are collected in a constraint check result
    When the command ScheduleTask is executed with payload:
      | Key            | Value                                                                                            |
      | taskIdentifier | "tasky-mc-taskface"                                                                              |
      | taskClassName  | "Sitegeist\\Bitzer\\Review\\Domain\\Task\\Review\\ReviewTask"                                    |
      | scheduledTime  | "2020-01-01T00:00:00+00:00"                                                                      |
      | agent          | "Sitegeist.Bitzer:TestingAgent"                                                                  |
      | object         | {"nodeAggregateIdentifier":"nody-mc-nodeface", "workspaceName":"live", "dimensionSpacePoint":{}} |
      | target         | "https://www.neos.io"                                                                            |
      | properties     | {"description":"task description"}                                                               |
    Then I expect the constraint check result to contain an exception of type "TargetIsInvalid" at path "target"
    And I expect the task "tasky-mc-taskface" not to exist

  Scenario: Schedule a review task
    Given I am authenticated as existing user "me"
    When the command ScheduleTask is executed with payload:
      | Key            | Value                                                                                            |
      | taskIdentifier | "tasky-mc-taskface"                                                                              |
      | taskClassName  | "Sitegeist\\Bitzer\\Review\\Domain\\Task\\Review\\ReviewTask"                                    |
      | scheduledTime  | "2020-01-02T00:00:00+00:00"                                                                      |
      | agent          | "Sitegeist.Bitzer:TestingAgent"                                                                  |
      | object         | {"nodeAggregateIdentifier":"nody-mc-nodeface", "workspaceName":"live", "dimensionSpacePoint":{}} |
      | properties     | {"description":"task description"}                                                               |
    Then I expect the task "tasky-mc-taskface" to exist
    And I expect this task to be of class "Sitegeist\Bitzer\Review\Domain\Task\Review\ReviewTask"
    And I expect this task to have action status "https://schema.org/PotentialActionStatus"
    And I expect this task to be scheduled to "2020-01-02T00:00:00+00:00"
    And I expect this task to be assigned to "Sitegeist.Bitzer:TestingAgent"
    And I expect this task to be about '{"nodeAggregateIdentifier":"nody-mc-nodeface", "workspaceName":"live", "dimensionSpacePoint":{}}'
    And I expect this task to have the target "http://localhost/nody-mc-nodeface@user-me.html"
    And I expect this task to have the properties:
      | Key         | Value            |
      | description | task description |
