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
}
