<?php
declare(strict_types=1);
namespace Sitegeist\Bitzer\Review\Domain\Task\Review;

use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Sitegeist\Bitzer\Application\Bitzer;
use Sitegeist\Bitzer\Domain\Task\ActionStatusType;
use Sitegeist\Bitzer\Domain\Task\Command\CancelTask;
use Sitegeist\Bitzer\Domain\Task\Command\RescheduleTask;
use Sitegeist\Bitzer\Domain\Task\Command\ScheduleTask;
use Sitegeist\Bitzer\Domain\Task\NodeAddress;
use Sitegeist\Bitzer\Domain\Task\Schedule;
use Sitegeist\Bitzer\Domain\Task\ScheduledTime;
use Sitegeist\Bitzer\Domain\Task\TaskClassName;
use Sitegeist\Bitzer\Domain\Task\TaskIdentifier;
use Sitegeist\Bitzer\Domain\Agent\Agent;

/**
 * The review task generator event listener
 */
class ReviewTaskZookeeper
{
    /**
     * @Flow\InjectConfiguration(package="Sitegeist.Bitzer", path="taskAutoGenerationEnabled")
     * @var bool
     */
    protected $taskAutoGenerationEnabled;

    /**
     * @Flow\InjectConfiguration(package="Sitegeist.Bitzer.Review", path="review.interval")
     * @var bool
     */
    protected $reviewInterval;

    /**
     * @Flow\InjectConfiguration(package="Sitegeist.Bitzer.Review", path="review.agent")
     * @var string
     */
    protected $reviewAgent;

    /**
     * @Flow\Inject
     * @var Bitzer
     */
    protected $bitzer;

    /**
     * @Flow\Inject
     * @var Schedule
     */
    protected $schedule;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    public function whenNodeAggregateWasPublished(TraversableNodeInterface $node, Workspace $workspace): void
    {
        if ($this->taskAutoGenerationEnabled && $workspace->getName() === 'live') {
            if ($node->isRemoved() && $node->getNodeType()->isOfType('Neos.Neos:Document')) {
                $object = NodeAddress::createLiveFromNode($node);
                $this->removeObsoleteTasks($object);
            } else {
                $document = $this->findClosestDocument($node);
                if ($document && $workspace->getName() === 'live') {
                    // we need to persist the node data objects for soft constraint checks
                    $this->persistenceManager->persistAll();
                    $object = NodeAddress::createLiveFromNode($document);
                    $this->scheduleReviewTask($object);
                }
            }
        }
    }

    public function whenTaskActionStatusWasUpdated(TaskIdentifier $taskIdentifier, ActionStatusType $actionStatus = null): void
    {
        $task = $this->schedule->findByIdentifier($taskIdentifier);
        if ($task instanceof ReviewTask && $actionStatus->equals(ActionStatusType::completed()) ) {

            $this->scheduleReviewTask(NodeAddress::createLiveFromNode($task->getObject()));
        }
    }

    private function removeObsoleteTasks(NodeAddress $object): void
    {
        foreach ($this->schedule->findActiveOrPotentialTasksForObject($object) as $task) {
            $command = new CancelTask($task->getIdentifier());
            $this->bitzer->handleCancelTask($command);
        }
    }

    private function scheduleReviewTask(NodeAddress $object): void
    {
        $taskClassName = TaskClassName::createFromString(ReviewTask::class);
        $tasks = $this->schedule->findActiveOrPotentialTasksForObject($object);
        $now = ScheduledTime::now();
        $scheduledTime = $now->add(new \DateInterval($this->reviewInterval));

        if (count($tasks) > 0) {
            foreach ($tasks as $task) {
                $command = new RescheduleTask(
                    $task->getIdentifier(),
                    $scheduledTime
                );
                $this->bitzer->handleRescheduleTask($command);
            }
        } else {
            $command = new ScheduleTask(
                TaskIdentifier::create(),
                $taskClassName,
                $scheduledTime,
                Agent::fromString($this->reviewAgent),
                $object,
                null,
                ['description' => 'auto generated review task']
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
}
