<?php
declare(strict_types=1);
namespace Sitegeist\Bitzer\Review\Domain\Task\Review;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\UserService;
use Psr\Http\Message\UriInterface;
use Sitegeist\Bitzer\Domain\Object\ObjectRepository;
use Sitegeist\Bitzer\Domain\Task\ActionStatusType;
use Sitegeist\Bitzer\Domain\Task\NodeAddress;
use Sitegeist\Bitzer\Domain\Task\TaskClassName;
use Sitegeist\Bitzer\Domain\Task\TaskFactoryInterface;
use Sitegeist\Bitzer\Domain\Task\TaskIdentifier;
use Sitegeist\Bitzer\Domain\Task\TaskInterface;
use Sitegeist\Bitzer\Infrastructure\UriService;

/**
 * The review task factory
 *
 * Creates task objects by using the implementation's constructor
 */
class ReviewTaskFactory implements TaskFactoryInterface
{
    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject
     * @var ObjectRepository
     */
    protected $objectRepository;

    /**
     * @Flow\Inject
     * @var UriService
     */
    protected $uriService;

    final public function createFromRawData(
        TaskIdentifier $identifier,
        TaskClassName $className,
        array $properties,
        \DateTimeImmutable $scheduledTime,
        ActionStatusType $actionStatus,
        string $agent,
        ?NodeAddress $object,
        ?UriInterface $target
    ): TaskInterface {
        $currentAgentWorkspaceName = $this->userService->getPersonalWorkspaceName();
        $objectInUserWorkspace = $object->withWorkspaceName($currentAgentWorkspaceName);

        $object = $this->objectRepository->findByAddress($object);
        $target = $this->uriService->findUriByAddress($objectInUserWorkspace);

        return new ReviewTask(
            $identifier,
            $properties,
            $scheduledTime,
            $actionStatus,
            $agent,
            $object,
            $target
        );
    }
}
