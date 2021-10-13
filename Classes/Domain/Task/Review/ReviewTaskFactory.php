<?php declare(strict_types=1);
namespace Sitegeist\Bitzer\Review\Domain\Task\Review;

use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\Flow\Annotations as Flow;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
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
use Sitegeist\Bitzer\Domain\Agent\Agent;

/**
 * The review task factory
 * Creates review task objects with proper targets
 *
 * @Flow\Scope("singleton")
 */
final class ReviewTaskFactory implements TaskFactoryInterface
{
    private UserService $userService;

    private ObjectRepository $objectRepository;

    public function __construct(
        UserService $userService,
        ObjectRepository $objectRepository
    ) {
        $this->userService = $userService;
        $this->objectRepository = $objectRepository;
    }

    final public function createFromRawData(
        TaskIdentifier $identifier,
        TaskClassName $className,
        array $properties,
        \DateTimeImmutable $scheduledTime,
        ActionStatusType $actionStatus,
        Agent $agent,
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
        $httpRequest = ServerRequest::fromGlobals();
        $actionRequest = ActionRequest::fromHttpRequest($httpRequest);
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
