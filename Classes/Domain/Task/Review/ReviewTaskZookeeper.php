<?php

declare(strict_types=1);

namespace Sitegeist\Bitzer\Review\Domain\Task\Review;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Sitegeist\Bitzer\Application\Bitzer;
use Sitegeist\Bitzer\Domain\Agent\AgentIdentifier;
use Sitegeist\Bitzer\Domain\Agent\Agents;
use Sitegeist\Bitzer\Domain\Task\ActionStatusType;
use Sitegeist\Bitzer\Domain\Task\Command\CancelTask;
use Sitegeist\Bitzer\Domain\Task\Command\RescheduleTask;
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
 */
#[Flow\Scope('singleton')]
final class ReviewTaskZookeeper
{
    public function __construct(
        private readonly bool $taskAutoGenerationEnabled,
        private readonly Bitzer $bitzer,
        private readonly Schedule $schedule,
        private readonly AgentRepository $agentRepository
    ) {
    }

    public function whenNodeAggregateWasPublished(TraversableNodeInterface $node, Workspace $workspace): void
    {
        /** @var Node $node */
        if ($this->taskAutoGenerationEnabled && $workspace->getName() === 'live') {
            if ($node->isRemoved()) {
                if ($node->getNodeType()->isOfType('Neos.Neos:Document')) {
                    $object = NodeAddress::liveFromNode($node);
                    $this->removeObsoleteTasks($object);
                }
            } else {
                $document = $this->findClosestDocument($node);

                if ($document) {
                    $agents = $this->getAgentsFromNode($document);
                    $object = NodeAddress::liveFromNode($document);

                    if (!$agents->isEmpty() && $document->getProperty('bitzerTaskInterval')) {
                        $interval = new \DateInterval($document->getProperty('bitzerTaskInterval'));
                        $description = $document->getProperty('bitzerTaskDescription')
                            ?: 'auto generated review task';
                        foreach ($agents as $agent) {
                            $this->scheduleReviewTask($object, $agent, $interval, $description);
                        }
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
        if ($task instanceof ReviewTask && $actionStatus === ActionStatusType::TYPE_COMPLETED && $task->getObject()) {
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
        $tasks = $this->schedule->findActiveOrPotentialTasksForObject($object, $taskClassName, $agent->identifier);
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
            }
        } else {
            $command = new ScheduleTask(
                TaskIdentifier::create(),
                $taskClassName,
                $scheduledTime,
                $agent->identifier,
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

    private function getAgentsFromNode(TraversableNodeInterface $node): Agents
    {
        $agentIds = array_map(
            fn (string $value): ?AgentIdentifier => AgentIdentifier::tryFromString($value),
            $node->getProperty('bitzerTaskAgents') ?: [],
        );

        $agents = [];
        foreach ($agentIds as $agentId) {
            if ($agentId instanceof AgentIdentifier) {
                $agent = $this->agentRepository->findByIdentifier(
                    $agentId
                );
                if ($agent instanceof Agent) {
                    $agents[] = $agent;
                }
            }
        }

        return new Agents(...$agents);
    }
}
