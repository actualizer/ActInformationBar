<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Migration;

use Act\InformationBar\Migration\Migration1787000200RemoveLegacyConfigKeys;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;

class RemoveLegacyConfigKeysTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getKernel()->getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testLegacyKeysAreRemovedButDefaultsSurvive(): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'ActInformationBar.config.backgroundColor',
            'configuration_value' => '{"_value":"#000000"}',
            'sales_channel_id' => null,
            'created_at' => '2026-08-16 00:00:00.000',
        ]);

        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'ActInformationBar.defaults.backgroundColor',
            'configuration_value' => '{"_value":"#000000"}',
            'sales_channel_id' => null,
            'created_at' => '2026-08-16 00:00:00.000',
        ]);

        (new Migration1787000200RemoveLegacyConfigKeys())->updateDestructive($this->connection);

        $legacy = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `system_config` WHERE `configuration_key` LIKE "ActInformationBar.config.%"'
        );
        $defaults = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `system_config` WHERE `configuration_key` LIKE "ActInformationBar.defaults.%"'
        );

        self::assertSame(0, (int) $legacy);
        self::assertGreaterThan(0, (int) $defaults, 'Defaults must not be deleted');
    }
}
