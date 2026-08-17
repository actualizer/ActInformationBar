<?php declare(strict_types=1);

namespace Act\InformationBar\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1787000000AddScheduleAndStylingColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `act_information_bar`
                ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT "" AFTER `sales_channel_id`,
                ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `name`,
                ADD COLUMN `start_date` DATETIME(3) NULL AFTER `active`,
                ADD COLUMN `end_date` DATETIME(3) NULL AFTER `start_date`,
                ADD COLUMN `full_width` TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN `display_duration` INT(11) NOT NULL DEFAULT 3,
                ADD COLUMN `show_button` TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN `button_target` VARCHAR(16) NOT NULL DEFAULT "_self",
                ADD COLUMN `text_color` VARCHAR(32) NULL,
                ADD COLUMN `background_color` VARCHAR(32) NULL,
                ADD COLUMN `padding_top` VARCHAR(16) NULL,
                ADD COLUMN `padding_bottom` VARCHAR(16) NULL,
                ADD COLUMN `font_size` VARCHAR(16) NULL,
                ADD COLUMN `button_text_color` VARCHAR(32) NULL,
                ADD COLUMN `button_text_color_hover` VARCHAR(32) NULL,
                ADD COLUMN `button_border_color` VARCHAR(32) NULL,
                ADD COLUMN `button_border_color_hover` VARCHAR(32) NULL,
                ADD COLUMN `button_border_width` VARCHAR(16) NULL,
                ADD COLUMN `button_background_color` VARCHAR(32) NULL,
                ADD COLUMN `button_background_color_hover` VARCHAR(32) NULL
        ');

        // The replacement index must exist before the unique one is dropped: InnoDB refuses
        // to drop an index that currently backs a foreign key without another index in place.
        $connection->executeStatement('
            ALTER TABLE `act_information_bar`
                ADD INDEX `idx.act_information_bar.schedule` (`sales_channel_id`, `active`, `start_date`)
        ');

        // The unique key allowed exactly one bar per sales channel, which is what 1.5.0 lifts.
        $connection->executeStatement('
            ALTER TABLE `act_information_bar`
                DROP INDEX `uniq.act_information_bar.sales_channel_id`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
