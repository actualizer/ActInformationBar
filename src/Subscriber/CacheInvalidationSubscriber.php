<?php declare(strict_types=1);

namespace Act\InformationBar\Subscriber;

use Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation\InformationBarTranslationDefinition;
use Act\InformationBar\Content\InformationBar\InformationBarDefinition;
use Act\InformationBar\Service\InformationBarCacheTag;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    private const ENTITIES = [
        InformationBarDefinition::ENTITY_NAME,
        InformationBarTranslationDefinition::ENTITY_NAME,
    ];

    public function __construct(private readonly CacheInvalidator $cacheInvalidator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'invalidate',
        ];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        foreach (self::ENTITIES as $entityName) {
            if ($event->getPrimaryKeys($entityName) !== []) {
                $this->cacheInvalidator->invalidate([InformationBarCacheTag::TAG]);

                return;
            }
        }
    }
}
