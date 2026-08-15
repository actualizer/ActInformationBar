<?php declare(strict_types=1);

namespace Act\InformationBar\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1786000000CreateInformationBarSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `act_information_bar` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.act_information_bar.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `fk.act_information_bar.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `act_information_bar_translation` (
                `act_information_bar_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `message` LONGTEXT NULL,
                `button_text` VARCHAR(255) NULL,
                `button_title` VARCHAR(255) NULL,
                `button_url` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`act_information_bar_id`, `language_id`),
                CONSTRAINT `fk.act_information_bar_translation.bar_id` FOREIGN KEY (`act_information_bar_id`)
                    REFERENCES `act_information_bar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.act_information_bar_translation.language_id` FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
