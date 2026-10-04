<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Cache;

use Act\InformationBar\ScheduledTask\ScheduleCacheTask;
use Act\InformationBar\ScheduledTask\ScheduleCacheTaskHandler;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PageCacheWiringTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testStorefrontPageIsTaggedForTheHttpCache(): void
    {
        $container = static::getContainer();
        $request = Request::create('/act-information-bar-cache-test');
        $container->get(RequestStack::class)->push($request);

        try {
            $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
            $container->get('event_dispatcher')->dispatch(new GenericPageLoadedEvent(new Page(), $context, $request));

            self::assertContains('act-information-bar', $container->get(CacheTagCollector::class)->get($request));
        } finally {
            $container->get(RequestStack::class)->pop();
        }
    }

    /**
     * Runs the real query: a bar that started two minutes ago, checked against a last run five minutes ago.
     */
    public function testTaskInvalidatesForABarThatStartedSinceTheLastRun(): void
    {
        $container = static::getContainer();
        $now = new \DateTimeImmutable('2030-03-01 12:05:00', new \DateTimeZone('UTC'));

        $container->get('act_information_bar.repository')->create([[
            'id' => Uuid::randomHex(),
            'name' => 'Scheduled',
            'active' => true,
            'startDate' => $now->modify('-2 minutes'),
            'translations' => [Defaults::LANGUAGE_SYSTEM => ['message' => 'Sale']],
        ]], Context::createCLIContext());

        $container->get(Connection::class)->executeStatement(
            'UPDATE scheduled_task SET last_execution_time = :lastRun WHERE name = :name',
            ['lastRun' => $now->modify('-5 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'name' => ScheduleCacheTask::getTaskName()]
        );

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(self::once())->method('invalidate')->with(['act-information-bar']);

        (new ScheduleCacheTaskHandler(
            $container->get('scheduled_task.repository'),
            $container->get('logger'),
            $container->get(Connection::class),
            $cacheInvalidator,
            new MockClock($now),
        ))->run();
    }

    public function testTaskIsRegisteredAndItsHandlerIsWired(): void
    {
        $container = static::getContainer();
        $container->get(TaskRegistry::class)->registerTasks();

        self::assertInstanceOf(ScheduleCacheTaskHandler::class, $container->get(ScheduleCacheTaskHandler::class));
        self::assertSame(
            '300',
            (string) $container->get(Connection::class)->fetchOne(
                'SELECT run_interval FROM scheduled_task WHERE name = :name',
                ['name' => ScheduleCacheTask::getTaskName()]
            )
        );
    }
}
