<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Migration;

use Act\InformationBar\Migration\Migration1786000100MigrateTextsFromConfig;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

class MigrateTextsFromConfigTest extends TestCase
{
    use IntegrationTestBehaviour;
    use KernelTestBehaviour;

    public function testCopiesGlobalConfiguredMessageIntoTranslation(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'ActInformationBar.config.message',
            'configuration_value' => json_encode(['_value' => 'Bestandstext'], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => null,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        (new Migration1786000100MigrateTextsFromConfig())->update($connection);

        $message = $connection->fetchOne(
            'SELECT t.`message`
             FROM `act_information_bar_translation` t
             INNER JOIN `act_information_bar` b ON b.`id` = t.`act_information_bar_id`
             WHERE b.`sales_channel_id` IS NULL'
        );

        self::assertSame('Bestandstext', $message);
    }

    public function testCreatesNothingWhenNoTextConfigured(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('DELETE FROM `act_information_bar`');
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` LIKE :key',
            ['key' => 'ActInformationBar.config.%']
        );

        (new Migration1786000100MigrateTextsFromConfig())->update($connection);

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM `act_information_bar`'));
    }

    public function testCreatesSalesChannelSpecificEntryWithSalesChannelLanguage(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);

        // Pick an existing language that differs from the system language, so this assertion
        // actually proves resolveLanguageId() reads the sales channel's own language instead of
        // trivially matching a fallback that could coincidentally share the same id.
        $salesChannelLanguageId = $connection->fetchOne(
            'SELECT `id` FROM `language` WHERE `id` != :systemLanguageId LIMIT 1',
            ['systemLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
        self::assertIsString($salesChannelLanguageId, 'Test fixtures must contain a second language.');

        // Temporarily point the existing sales channel to that language. IntegrationTestBehaviour
        // rolls this back after the test, so no fixture is created or permanently altered.
        $connection->update(
            'sales_channel',
            ['language_id' => $salesChannelLanguageId],
            ['id' => $salesChannelId]
        );

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'ActInformationBar.config.message',
            'configuration_value' => json_encode(['_value' => 'Kanaltext'], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => $salesChannelId,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        (new Migration1786000100MigrateTextsFromConfig())->update($connection);

        $row = $connection->fetchAssociative(
            'SELECT t.`message`, t.`language_id`
             FROM `act_information_bar_translation` t
             INNER JOIN `act_information_bar` b ON b.`id` = t.`act_information_bar_id`
             WHERE b.`sales_channel_id` = :scId',
            ['scId' => $salesChannelId]
        );

        self::assertIsArray($row);
        self::assertSame('Kanaltext', $row['message']);
        self::assertSame($salesChannelLanguageId, $row['language_id']);
        self::assertNotSame(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $row['language_id']);
    }

    public function testCreatesNothingWhenConfiguredValueIsEmptyString(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('DELETE FROM `act_information_bar`');
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` LIKE :key',
            ['key' => 'ActInformationBar.config.%']
        );

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'ActInformationBar.config.message',
            'configuration_value' => json_encode(['_value' => ''], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => null,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        (new Migration1786000100MigrateTextsFromConfig())->update($connection);

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM `act_information_bar`'));
    }

    public function testRunningUpdateTwiceIsIdempotentAndDoesNotOverwriteExistingTranslations(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('DELETE FROM `act_information_bar`');
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` LIKE :key',
            ['key' => 'ActInformationBar.config.%']
        );

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'ActInformationBar.config.message',
            'configuration_value' => json_encode(['_value' => 'Originaltext'], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => null,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        $migration = new Migration1786000100MigrateTextsFromConfig();
        $migration->update($connection);

        // Simulate a merchant editing the migrated text afterwards, e.g. via the new admin UI.
        $connection->executeStatement(
            "UPDATE `act_information_bar_translation` SET `message` = 'Manuell bearbeiteter Text'"
        );

        $migration->update($connection);

        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM `act_information_bar`'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM `act_information_bar_translation`'));
        self::assertSame(
            'Manuell bearbeiteter Text',
            $connection->fetchOne('SELECT `message` FROM `act_information_bar_translation`')
        );
    }
}
