<?php
declare(strict_types=1);
namespace Sitegeist\Bitzer\Review\Domain\Task\Review;

use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Request;
use Neos\Flow\Http\Uri;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Neos\Service\UserService;
use Psr\Http\Message\UriInterface;
use Sitegeist\Bitzer\Domain\Object\ObjectRepository;
use Sitegeist\Bitzer\Domain\Task\ActionStatusType;
use Sitegeist\Bitzer\Domain\Task\NodeAddress;
use Sitegeist\Bitzer\Domain\Task\TaskClassName;
use Sitegeist\Bitzer\Domain\Task\TaskFactoryInterface;
use Sitegeist\Bitzer\Domain\Task\TaskIdentifier;
use Sitegeist\Bitzer\Domain\Task\TaskInterface;

/**
 * The review task factory
 *
 * Creates review task objects with proper targets
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
        $objectAddressInUserWorkspace = $currentAgentWorkspaceName
            ? $object->withWorkspaceName($currentAgentWorkspaceName)
            : $object;

        $object = $this->objectRepository->findByAddress($object);
        $objectInUserWorkspace = $this->objectRepository->findByAddress($objectAddressInUserWorkspace);
        $target = $objectInUserWorkspace ? $this->buildBackendUri($objectInUserWorkspace) : null;

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

    private function buildBackendUri(TraversableNodeInterface $object): Uri
    {
        $request = Request::createFromEnvironment();
        $actionRequest = new ActionRequest($request);
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($actionRequest);
        $uriBuilder->setCreateAbsoluteUri(true);

        return new Uri($uriBuilder->uriFor(
            'index',
            ['node' => $object],
            'Backend',
            'Neos.Neos.Ui'
        ));
    }
}
