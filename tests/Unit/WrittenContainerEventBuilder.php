<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class WrittenContainerEventBuilder
{
    /**
     * @param array<string, list<string>> $writes primary keys per entity name
     */
    public static function build(array $writes): EntityWrittenContainerEvent
    {
        $context = Context::createCLIContext();
        $events = [];

        foreach ($writes as $entity => $primaryKeys) {
            $events[] = new EntityWrittenEvent(
                $entity,
                array_map(
                    static fn (string $primaryKey): EntityWriteResult => new EntityWriteResult($primaryKey, [], $entity, EntityWriteResult::OPERATION_UPDATE),
                    $primaryKeys
                ),
                $context
            );
        }

        return new EntityWrittenContainerEvent($context, new NestedEventCollection($events), []);
    }
}
