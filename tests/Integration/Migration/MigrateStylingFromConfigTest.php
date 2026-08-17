<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Migration;

use Act\InformationBar\Migration\Migration1787000100MigrateStylingFromConfig;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;

class MigrateStylingFromConfigTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testStylingIsCopiedFromConfigIntoTheBar(): void
    {
        $barId = Uuid::randomBytes();
        $this->connection->insert('act_information_bar', [
            'id' => $barId,
            'sales_channel_id' => null,
            'name' => '',
            'active' => 1,
            'created_at' => '2026-08-16 00:00:00.000',
        ]);

        $this->writeConfig('backgroundColor', '#123456');
        $this->writeConfig('paddingTop', '22px');
        $this->writeConfig('showButton', true);

        (new Migration1787000100MigrateStylingFromConfig())->update($this->connection);

        $row = $this->connection->fetchAssociative(
            'SELECT `background_color`, `padding_top`, `show_button`, `name` FROM `act_information_bar` WHERE `id` = ?',
            [$barId]
        );

        self::assertSame('#123456', $row['background_color']);
        self::assertSame('22px', $row['padding_top']);
        self::assertSame(1, (int) $row['show_button']);
        self::assertNotSame('', $row['name'], 'Migration must give the bar a name');
    }

    public function testExistingDateRangeBecomesAScheduledBar(): void
    {
        $barId = Uuid::randomBytes();
        $this->connection->insert('act_information_bar', [
            'id' => $barId,
            'sales_channel_id' => null,
            'name' => '',
            'active' => 1,
            'created_at' => '2026-08-16 00:00:00.000',
        ]);

        $this->writeConfig('startDate', '2026-11-29T00:00:00.000Z');
        $this->writeConfig('endDate', '2026-11-30T00:00:00.000Z');

        (new Migration1787000100MigrateStylingFromConfig())->update($this->connection);

        $row = $this->connection->fetchAssociative(
            'SELECT `start_date`, `end_date` FROM `act_information_bar` WHERE `id` = ?',
            [$barId]
        );

        self::assertNotNull($row['start_date']);
        self::assertStringStartsWith('2026-11-29', $row['start_date']);
        self::assertNotNull($row['end_date'], 'Migration must write end_date too, or the bar never stops running');
        self::assertStringStartsWith('2026-11-30', $row['end_date']);
    }

    public function testDefaultsAreSeededFromTheSameValues(): void
    {
        $this->writeConfig('backgroundColor', '#abcdef');

        (new Migration1787000100MigrateStylingFromConfig())->update($this->connection);

        $value = $this->connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = ?',
            ['ActInformationBar.defaults.backgroundColor']
        );

        self::assertNotFalse($value);

        // SystemConfigService::get() reads the value from under the "_value" key specifically,
        // not from anywhere in the JSON blob - assert the structure it actually depends on.
        $decoded = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('_value', $decoded);
        self::assertSame('#abcdef', $decoded['_value']);
    }

    public function testRunningMigrationTwiceDoesNotDuplicateDefaults(): void
    {
        $this->writeConfig('backgroundColor', '#abcdef');

        $migration = new Migration1787000100MigrateStylingFromConfig();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = ?',
            ['ActInformationBar.defaults.backgroundColor']
        );

        self::assertCount(1, $rows, 'Re-running the migration must update the existing defaults row, not duplicate it');
        self::assertStringContainsString('#abcdef', (string) $rows[0]['configuration_value']);
    }

    /**
     * The legacy value carries no offset - exactly the shape the original bug mishandled.
     * This environment's date.timezone is Europe/Berlin (verified by
     * InformationBarDefaultsControllerTest::testTimezoneFallsBackToServerTimezone), where
     * 29.11. 00:00 is CET (UTC+1), so the correct stored instant is 28.11. 23:00 UTC.
     */
    public function testOffsetlessStartDateIsInterpretedInTheServerTimezoneNotUtc(): void
    {
        $barId = Uuid::randomBytes();
        $this->connection->insert('act_information_bar', [
            'id' => $barId,
            'sales_channel_id' => null,
            'name' => '',
            'active' => 1,
            'created_at' => '2026-08-16 00:00:00.000',
        ]);

        $this->writeConfig('startDate', '2026-11-29 00:00:00');

        (new Migration1787000100MigrateStylingFromConfig())->update($this->connection);

        $row = $this->connection->fetchAssociative(
            'SELECT `start_date` FROM `act_information_bar` WHERE `id` = ?',
            [$barId]
        );

        self::assertSame('2026-11-28 23:00:00.000', $row['start_date']);
    }

    public function testDefaultsAreSeededPerSalesChannelNotGlobally(): void
    {
        // system_config.sales_channel_id has a foreign key onto sales_channel, so this needs
        // two real channels from the fixtures rather than arbitrary random UUIDs.
        $salesChannelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM `sales_channel` LIMIT 2');
        self::assertCount(2, $salesChannelIds, 'Test fixtures must contain at least two sales channels.');
        [$channelA, $channelB] = $salesChannelIds;

        $this->writeConfig('backgroundColor', '#111111', $channelA);
        $this->writeConfig('backgroundColor', '#222222', $channelB);

        (new Migration1787000100MigrateStylingFromConfig())->update($this->connection);

        // Filtered to the two channels under test: a pre-existing global (NULL channel) row
        // from the plugin's own config.xml defaults would otherwise inflate the row count.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`sales_channel_id`)) AS sales_channel_id, `configuration_value`
             FROM `system_config`
             WHERE `configuration_key` = ? AND LOWER(HEX(`sales_channel_id`)) IN (?, ?)',
            ['ActInformationBar.defaults.backgroundColor', $channelA, $channelB]
        );

        self::assertCount(2, $rows, 'Each sales channel must get its own defaults row, not one shared global row');

        $bySalesChannel = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['configuration_value'], true, flags: JSON_THROW_ON_ERROR);
            $bySalesChannel[$row['sales_channel_id']] = $decoded['_value'];
        }

        self::assertSame('#111111', $bySalesChannel[strtolower($channelA)]);
        self::assertSame('#222222', $bySalesChannel[strtolower($channelB)]);
    }

    private function writeConfig(string $key, mixed $value, ?string $salesChannelId = null): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'ActInformationBar.config.' . $key,
            'configuration_value' => json_encode(['_value' => $value], JSON_THROW_ON_ERROR),
            'sales_channel_id' => $salesChannelId === null ? null : Uuid::fromHexToBytes($salesChannelId),
            'created_at' => '2026-08-16 00:00:00.000',
        ]);
    }
}
