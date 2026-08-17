<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Content;

use Act\InformationBar\Content\InformationBar\InformationBarCollection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class InformationBarDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use KernelTestBehaviour;

    public function testWritesAndReadsTranslatedFields(): void
    {
        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('act_information_bar.repository');
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $repository->create([[
            'id' => $id,
            'salesChannelId' => null,
            // 'name' became required in Task 2's schema; unrelated to what this test asserts.
            'name' => 'Systemsprache Bar',
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'message' => 'Systemsprache',
                    'buttonText' => 'Mehr',
                    'buttonTitle' => 'Titel',
                    'buttonUrl' => '/mehr',
                ],
            ],
        ]], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('translations');

        /** @var InformationBarCollection $result */
        $result = $repository->search($criteria, $context)->getEntities();
        $entity = $result->first();

        self::assertNotNull($entity);
        self::assertSame('Systemsprache', $entity->getMessage());
        self::assertSame('/mehr', $entity->getButtonUrl());
        self::assertNotNull($entity->getTranslations());
        self::assertCount(1, $entity->getTranslations());
    }

    public function testScheduleAndStylingRoundTrip(): void
    {
        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('act_information_bar.repository');
        $id = Uuid::randomHex();
        $context = Context::createCLIContext();

        $repository->create([[
            'id' => $id,
            'name' => 'Betriebsurlaub',
            // Both booleans below are deliberately set to the OPPOSITE of the column/property
            // default (active default true, showButton default false), so the assertions
            // fail if the field were dropped from the definition instead of actually written.
            'active' => false,
            'startDate' => new \DateTimeImmutable('2026-09-16 00:00:00'),
            'endDate' => new \DateTimeImmutable('2026-09-30 23:59:59'),
            'backgroundColor' => '#c00000',
            'showButton' => true,
            'displayDuration' => 5,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['message' => 'Wir haben Betriebsurlaub'],
            ],
        ]], $context);

        $bar = $repository->search(new Criteria([$id]), $context)->getEntities()->first();

        self::assertNotNull($bar);
        self::assertSame('Betriebsurlaub', $bar->getName());
        self::assertFalse($bar->getActive());
        self::assertSame('2026-09-16', $bar->getStartDate()?->format('Y-m-d'));
        self::assertSame('#c00000', $bar->getBackgroundColor());
        self::assertTrue($bar->getShowButton());
        self::assertSame(5, $bar->getDisplayDuration());
    }
}
