<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

class AddScheduleAndStylingColumnsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);
    }

    public function testAllColumnsExist(): void
    {
        $columns = $this->connection->fetchFirstColumn(
            'SHOW COLUMNS FROM `act_information_bar`'
        );

        $expected = [
            'name', 'active', 'start_date', 'end_date',
            'full_width', 'display_duration', 'show_button', 'button_target',
            'text_color', 'background_color', 'padding_top', 'padding_bottom', 'font_size',
            'button_text_color', 'button_text_color_hover',
            'button_border_color', 'button_border_color_hover', 'button_border_width',
            'button_background_color', 'button_background_color_hover',
        ];

        foreach ($expected as $column) {
            self::assertContains($column, $columns, "Column {$column} is missing");
        }
    }

    public function testUniqueIndexOnSalesChannelIsGone(): void
    {
        $indexes = $this->connection->fetchAllAssociative(
            'SHOW INDEX FROM `act_information_bar`'
        );

        $names = array_column($indexes, 'Key_name');

        self::assertNotContains('uniq.act_information_bar.sales_channel_id', $names);
    }

    public function testScheduleIndexExists(): void
    {
        // SHOW INDEX does not support ORDER BY; sort by Seq_in_index in PHP instead.
        $indexes = $this->connection->fetchAllAssociative(
            'SHOW INDEX FROM `act_information_bar` WHERE `Key_name` = "idx.act_information_bar.schedule"'
        );

        usort($indexes, static fn (array $a, array $b): int => $a['Seq_in_index'] <=> $b['Seq_in_index']);
        $columns = array_column($indexes, 'Column_name');

        self::assertSame(['sales_channel_id', 'active', 'start_date'], $columns);
    }

    public function testTwoBarsForTheSameSalesChannelCanBeStored(): void
    {
        // NULL is not a useful probe here: MySQL allows unlimited NULLs under a UNIQUE KEY,
        // so a non-null, existing sales channel id is required to actually exercise the drop.
        $salesChannelId = $this->connection->fetchOne('SELECT `id` FROM `sales_channel` LIMIT 1');
        self::assertNotFalse($salesChannelId, 'No sales channel found to test against');

        $this->connection->beginTransaction();

        try {
            for ($i = 0; $i < 2; ++$i) {
                $this->connection->insert('act_information_bar', [
                    'id' => hex2bin(str_repeat('a', 31) . (string) $i),
                    'sales_channel_id' => $salesChannelId,
                    'name' => 'Bar ' . $i,
                    'active' => 1,
                    'created_at' => '2026-08-16 00:00:00.000',
                ]);
            }

            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `act_information_bar` WHERE `name` LIKE "Bar %"'
            );

            self::assertSame(2, $count);
        } finally {
            $this->connection->rollBack();
        }
    }
}
