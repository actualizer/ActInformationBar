<?php declare(strict_types=1);

namespace Act\InformationBar\Subscriber;

use Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation\InformationBarTranslationDefinition;
use Act\InformationBar\Service\Text\BarTextFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The text fields are flagged AllowHtml(false), which switches off the core's strip_tags().
 * This subscriber is what keeps unfiltered markup out of the database; never ship one without the other.
 */
#[Package('discovery')]
class BarTextWriteSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly BarTextFilter $filter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'onEntityWrite',
        ];
    }

    public function onEntityWrite(EntityWriteEvent $event): void
    {
        foreach ($event->getCommandsForEntity(InformationBarTranslationDefinition::ENTITY_NAME) as $command) {
            if ($command instanceof DeleteCommand) {
                continue;
            }

            foreach (array_keys(BarTextFilter::STORAGE_COLUMNS) as $column) {
                if (!$command->hasField($column)) {
                    continue;
                }

                $value = $command->getPayload()[$column] ?? null;
                if (!is_string($value)) {
                    continue;
                }

                $command->addPayload($column, $this->filter->cleanStorageColumn($column, $value));
            }
        }
    }
}
