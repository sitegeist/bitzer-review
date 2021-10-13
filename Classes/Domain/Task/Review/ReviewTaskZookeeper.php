<?php declare(strict_types=1);
namespace Sitegeist\Bitzer\Review\Domain\Task\Review;

use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Sitegeist\Bitzer\Application\Bitzer;
use Sitegeist\Bitzer\Domain\Agent\AgentIdentifier;
use Sitegeist\Bitzer\Domain\Task\ActionStatusType;
use Sitegeist\Bitzer\Domain\Task\Command\CancelTask;
use Sitegeist\Bitzer\Domain\Task\Command\RescheduleTask;
use Sitegeist\Bitzer\Domain\Task\Command\ReassignTask;
use Sitegeist\Bitzer\Domain\Task\Command\ScheduleTask;
use Sitegeist\Bitzer\Domain\Task\Command\SetTaskProperties;
use Sitegeist\Bitzer\Domain\Task\NodeAddress;
use Sitegeist\Bitzer\Domain\Task\Schedule;
use Sitegeist\Bitzer\Domain\Task\ScheduledTime;
use Sitegeist\Bitzer\Domain\Task\TaskClassName;
use Sitegeist\Bitzer\Domain\Task\TaskIdentifier;
use Sitegeist\Bitzer\Domain\Agent\Agent;
use Sitegeist\Bitzer\Domain\Agent\AgentRepository;

/**
 * The review task generator event listener
 *
 * @Flow\Scope("singleton")
 */
final class ReviewTaskZookeeper
{
    private bool $taskAutoGenerationEnabled;

    private Bitzer $bitzer;

    private Schedule $schedule;

    private AgentRepository $agentRepository;

    public function __construct(
        bool $taskAutoGenerationEnabled,
        Bitzer $bitzer,
        Schedule $schedule,
        AgentRepository $agentRepository
    ) {
        $this->taskAutoGenerationEnabled = $taskAutoGenerationEnabled;
        $this->bitzer = $bitzer;
        $this->schedule = $schedule;
        $this->agentRepository = $agentRepository;
    }

    public function whenNodeAggregateWasPublished(TraversableNodeInterface $node, Workspace $workspace): void
    {
        if ($this->taskAutoGenerationEnabled && $workspace->getName() === 'live') {
            if ($node->isRemoved() && $node->getNodeType()->isOfType('Neos.Neos:Document')) {
                $object = NodeAddress::liveFromNode($node);
                $this->removeObsoleteTasks($object);
            } else {
                $document = $this->findClosestDocument($node);

                if ($document) {
                    $agent = $this->getAgentFromNode($document);
                    $object = NodeAddress::liveFromNode($document);

                    if ($agent && $document->getProperty('bitzerTaskInterval')) {
                        $interval = new \DateInterval($document->getProperty('bitzerTaskInterval'));
                        $description = $document->getProperty('bitzerTaskDescription')
                            ?: 'auto generated review task';
                        $this->scheduleReviewTask($object, $agent, $interval, $description);
                    } else {
                        $this->removeObsoleteTasks($object);
                    }
                }
            }
        }
    }

    public function whenTaskActionStatusWasUpdated(TaskIdentifier $taskIdentifier, ActionStatusType $actionStatus = null): void
    {
        $task = $this->schedule->findByIdentifier($taskIdentifier);
        if ($task instanceof ReviewTask && $actionStatus->equals(ActionStatusType::completed()) ) {
            $this->scheduleReviewTask(
                NodeAddress::liveFromNode($task->getObject()),
                $task->getAgent(),
                new \DateInterval($task->getObject()->getProperty('bitzerTaskInterval')),
                $task->getDescription()
            );
        }
    }

    private function removeObsoleteTasks(NodeAddress $object): void
    {
        foreach ($this->schedule->findActiveOrPotentialTasksForObject($object) as $task) {
            $command = new CancelTask($task->getIdentifier());
            $this->bitzer->handleCancelTask($command);
        }
    }

    private function scheduleReviewTask(
        NodeAddress $object,
        Agent $agent,
        \DateInterval $interval,
        string $description
    ): void {
        $taskClassName = TaskClassName::createFromString(ReviewTask::class);
        $tasks = $this->schedule->findActiveOrPotentialTasksForObject($object);
        $now = ScheduledTime::now();
        $scheduledTime = $now->add($interval);

        if (count($tasks) > 0) {
            foreach ($tasks as $task) {
                $command = new RescheduleTask(
                    $task->getIdentifier(),
                    $scheduledTime
                );
                $this->bitzer->handleRescheduleTask($command);

                if ($task->getDescription() !== $description) {
                    $setTaskProperties = new SetTaskProperties(
                        $task->getIdentifier(),
                        array_merge(
                            $task->getProperties(),
                            [
                                'description' => $description
                            ]
                        )
                    );
                    $this->bitzer->handleSetTaskProperties($setTaskProperties);
                }

                if (!$task->getAgent()->equals($agent)) {
                    $command = new ReassignTask(
                        $task->getIdentifier(),
                        $agent
                    );
                    $this->bitzer->handleReassignTask($command);
                }
            }
        } else {
            $command = new ScheduleTask(
                TaskIdentifier::create(),
                $taskClassName,
                $scheduledTime,
                $agent,
                $object,
                null,
                ['description' => $description]
            );
            $this->bitzer->handleScheduleTask($command);
        }
    }

    private function findClosestDocument(TraversableNodeInterface $node): ?TraversableNodeInterface
    {
        if ($node->getNodeType()->isOfType('Neos.Neos:Document')) {
            return $node;
        }
        try {
            return $this->findClosestDocument($node->findParentNode());
        } catch (NodeException $e) {
            return null;
        }
    }

    private function getAgentFromNode(TraversableNodeInterface $node): ?Agent
    {
        if (empty($node->getProperty('bitzerTaskAgent'))) {
            return null;
        }

        return $this->agentRepository->findByIdentifier(
            AgentIdentifier::fromString($node->getProperty('bitzerTaskAgent'))
        );
    }
}
