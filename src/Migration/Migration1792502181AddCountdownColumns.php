<?php declare(strict_types=1);

namespace Act\InformationBar\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1792502181AddCountdownColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792502181;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `act_information_bar`
                ADD COLUMN `countdown_target` DATETIME(3) NULL,
                ADD COLUMN `countdown_color` VARCHAR(32) NULL,
                ADD COLUMN `countdown_label_color` VARCHAR(32) NULL,
                ADD COLUMN `countdown_font` VARCHAR(16) NULL,
                ADD COLUMN `button_border_radius` VARCHAR(16) NULL
        ');

        $connection->executeStatement('
            ALTER TABLE `act_information_bar_translation`
                ADD COLUMN `countdown_expired_text` VARCHAR(255) NULL
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
