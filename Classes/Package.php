<?php
declare(strict_types=1);
namespace Sitegeist\Bitzer\Review;

use Neos\ContentRepository\Domain\Service\PublishingService;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package as BasePackage;
use Sitegeist\Bitzer\Review\Domain\Task\Review\ReviewTaskZookeeper;

/**
 * The Sitegeist.Bitzer.Review
 */
class Package extends BasePackage
{
    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $dispatcher->connect(
            PublishingService::class,
            'nodePublished',
            ReviewTaskZookeeper::class,
            'whenNodeAggregateWasPublished'
        );
    }
}
