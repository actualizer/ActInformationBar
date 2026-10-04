<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit\Subscriber;

use Act\InformationBar\Subscriber\CacheInvalidationSubscriber;
use Act\InformationBar\Tests\Unit\WrittenContainerEventBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Uuid\Uuid;

final class CacheInvalidationSubscriberTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function barEntities(): iterable
    {
        yield 'bar' => ['act_information_bar'];
        yield 'bar translation' => ['act_information_bar_translation'];
    }

    #[DataProvider('barEntities')]
    public function testInvalidatesTheBarTagWhenABarIsWritten(string $entityName): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(self::once())->method('invalidate')->with(['act-information-bar']);

        (new CacheInvalidationSubscriber($cacheInvalidator))->invalidate(
            WrittenContainerEventBuilder::build([$entityName => [Uuid::randomHex()]])
        );
    }

    public function testIgnoresWritesOfOtherEntities(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(self::never())->method('invalidate');

        (new CacheInvalidationSubscriber($cacheInvalidator))->invalidate(
            WrittenContainerEventBuilder::build(['product' => [Uuid::randomHex()]])
        );
    }
}
