<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit\ScheduledTask;

use Act\InformationBar\ScheduledTask\ScheduleCacheTask;
use Act\InformationBar\ScheduledTask\ScheduleCacheTaskHandler;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

final class ScheduleCacheTaskHandlerTest extends TestCase
{
    private const NOW = '2030-03-01 12:05:00.000';
    private const LAST_RUN = '2030-03-01 12:00:00.000';

    public function testInvalidatesWhenABarStartedOrEndedSinceTheLastRun(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(self::once())->method('invalidate')->with(['act-information-bar']);

        $this->createHandler($this->connection(self::LAST_RUN, 1), $cacheInvalidator)->run();
    }

    public function testDoesNotInvalidateWhenNoWindowWasCrossed(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(self::never())->method('invalidate');

        $this->createHandler($this->connection(self::LAST_RUN, false), $cacheInvalidator)->run();
    }

    public function testChecksTheWindowBetweenLastRunAndNow(): void
    {
        $windowParams = [];

        $this->createHandler($this->connection(self::LAST_RUN, false, $windowParams))->run();

        self::assertSame(['since' => self::LAST_RUN, 'now' => self::NOW], $windowParams);
    }

    public function testUsesTheTaskIntervalAsWindowOnTheFirstRun(): void
    {
        $windowParams = [];

        $this->createHandler($this->connection(false, false, $windowParams))->run();

        self::assertSame(300, ScheduleCacheTask::getDefaultInterval());
        self::assertSame(['since' => '2030-03-01 12:00:00.000', 'now' => self::NOW], $windowParams);
    }

    /**
     * @param array<string, mixed> $windowParams receives the parameters of the window query
     */
    private function connection(string|false $lastRun, int|false $crossed, array &$windowParams = []): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            static function (string $sql, array $params = []) use ($lastRun, $crossed, &$windowParams): mixed {
                if (str_contains($sql, 'scheduled_task')) {
                    return $lastRun;
                }

                $windowParams = $params;

                return $crossed;
            }
        );

        return $connection;
    }

    private function createHandler(Connection $connection, ?CacheInvalidator $cacheInvalidator = null): ScheduleCacheTaskHandler
    {
        return new ScheduleCacheTaskHandler(
            new StaticEntityRepository([]),
            new NullLogger(),
            $connection,
            $cacheInvalidator ?? $this->createMock(CacheInvalidator::class),
            new MockClock(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'))),
        );
    }
}
