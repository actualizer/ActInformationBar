<?php declare(strict_types=1);

namespace Act\InformationBar\ScheduledTask;

use Act\InformationBar\Service\InformationBarCacheTag;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Cached pages do not notice when a bar's window opens or closes without a write.
 */
#[AsMessageHandler(handles: ScheduleCacheTask::class)]
final class ScheduleCacheTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly Connection $connection,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone('UTC'));

        // The executor stores last_execution_time after run(), so it still holds the previous run here.
        $lastRun = $this->connection->fetchOne(
            'SELECT last_execution_time FROM scheduled_task WHERE name = :name',
            ['name' => ScheduleCacheTask::getTaskName()]
        );

        $since = \is_string($lastRun)
            ? $lastRun
            : $now->modify(\sprintf('-%d seconds', ScheduleCacheTask::getDefaultInterval()))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $crossed = $this->connection->fetchOne(
            'SELECT 1 FROM act_information_bar
              WHERE active = 1
                AND ((start_date > :since AND start_date <= :now)
                  OR (end_date > :since AND end_date <= :now))
              LIMIT 1',
            ['since' => $since, 'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );

        if ($crossed !== false) {
            $this->cacheInvalidator->invalidate([InformationBarCacheTag::TAG]);
        }
    }
}
