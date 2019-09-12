<?php
declare(strict_types=1);

namespace Sitegeist\Bitzer\Review\Domain\Task\Review;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\UriInterface;
use Sitegeist\Bitzer\Domain\Task\Command\ActivateTask;
use Sitegeist\Bitzer\Domain\Task\Command\CancelTask;
use Sitegeist\Bitzer\Domain\Task\Command\CompleteTask;
use Sitegeist\Bitzer\Domain\Task\Command\ReassignTask;
use Sitegeist\Bitzer\Domain\Task\Command\RescheduleTask;
use Sitegeist\Bitzer\Domain\Task\Command\ScheduleTask;
use Sitegeist\Bitzer\Domain\Task\Command\SetNewTaskObject;
use Sitegeist\Bitzer\Domain\Task\Command\SetNewTaskTarget;
use Sitegeist\Bitzer\Domain\Task\Command\SetTaskProperties;
use Sitegeist\Bitzer\Domain\Task\ConstraintCheckPluginInterface;
use Sitegeist\Bitzer\Domain\Task\ConstraintCheckResult;
use Sitegeist\Bitzer\Domain\Task\Exception\ObjectIsUndefined;
use Sitegeist\Bitzer\Domain\Task\Exception\TargetIsInvalid;
use Sitegeist\Bitzer\Domain\Task\NodeAddress;

/**
 * The constraint check plugins for commands operating on review tasks
 *
 * @Flow\Proxy(false)
 */
final class ReviewTaskConstraintCheckPlugin implements ConstraintCheckPluginInterface
{
    public function checkScheduleTask(ScheduleTask $command, ConstraintCheckResult $constraintCheckResult = null): void
    {
        $this->requireObjectToBeDefined($command->getObject(), $constraintCheckResult);
        $this->requireTargetToBeUnset($command->getTarget(), $constraintCheckResult);
    }

    public function checkRescheduleTask(
        RescheduleTask $command,
        ConstraintCheckResult $constraintCheckResult = null
    ): void {
        // rescheduling tasks has no effect on objects
    }

    public function checkReassignTask(ReassignTask $command, ConstraintCheckResult $constraintCheckResult = null): void
    {
        // reassigning tasks has no effect on objects
    }

    public function checkSetNewTaskTarget(
        SetNewTaskTarget $command,
        ConstraintCheckResult $constraintCheckResult = null
    ): void {
        $this->requireTargetToBeUnset($command->getTarget());
    }

    public function checkSetNewTaskObject(
        SetNewTaskObject $command,
        ConstraintCheckResult $constraintCheckResult = null
    ): void {
        $this->requireObjectToBeDefined($command->getObject(), $constraintCheckResult);
    }

    public function checkSetTaskProperties(
        SetTaskProperties $command,
        ConstraintCheckResult $constraintCheckResult = null
    ): void {
        // setting tasks has no effect on objects
    }

    public function checkCancelTask(CancelTask $command, ConstraintCheckResult $constraintCheckResult = null): void
    {
        // cancelling tasks has no direct effect on objects
    }

    public function checkActivateTask(ActivateTask $command, ConstraintCheckResult $constraintCheckResult = null): void
    {
        // activating tasks has no effect on objects
    }

    public function checkCompleteTask(CompleteTask $command, ConstraintCheckResult $constraintCheckResult = null): void
    {
        // completing tasks has no effect on objects
    }

    private function requireObjectToBeDefined(
        ?NodeAddress $object,
        ConstraintCheckResult $constraintCheckResult = null
    ): void {
        if (!$object) {
            $exception = ObjectIsUndefined::althoughExpected();
            if ($constraintCheckResult) {
                $constraintCheckResult->registerFailedCheck('object', $exception);
            } else {
                throw $exception;
            }
        }
    }

    private function requireTargetToBeUnset(
        ?UriInterface $target,
        ConstraintCheckResult $constraintCheckResult = null
    ): void {
        if ($target) {
            $exception = new TargetIsInvalid('Review tasks generate their own targets, so target must not be set explicitly.', 1568214974);
            if ($constraintCheckResult) {
                $constraintCheckResult->registerFailedCheck('target', $exception);
            } else {
                throw $exception;
            }
        }
    }
}
