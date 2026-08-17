<?php declare(strict_types=1);

namespace Act\InformationBar\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1787000200RemoveLegacyConfigKeys extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787000200;
    }

    public function update(Connection $connection): void
    {
    }

    /**
     * Destructive on purpose: the values were copied into act_information_bar by
     * Migration1787000100. Removing them earlier would break a rollback to 1.4.0.
     */
    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` LIKE "ActInformationBar.config.%"'
        );
    }
}
