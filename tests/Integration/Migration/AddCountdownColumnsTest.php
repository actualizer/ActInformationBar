<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Migration;

use Act\InformationBar\Migration\Migration1792502181AddCountdownColumns;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

class AddCountdownColumnsTest extends TestCase
{
    private const BAR_COLUMNS = [
        'countdown_target', 'countdown_color', 'countdown_label_color', 'countdown_font', 'button_border_radius',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);
    }

    protected function tearDown(): void
    {
        // DDL cannot be rolled back; never leave the shared test DB without the migrated columns.
        $barMissing = array_diff(self::BAR_COLUMNS, array_keys($this->columns('act_information_bar'))) !== [];
        $translationMissing = !array_key_exists('countdown_expired_text', $this->columns('act_information_bar_translation'));

        if ($barMissing || $translationMissing) {
            $this->dropNewColumns();
            (new Migration1792502181AddCountdownColumns())->update($this->connection);
        }

        // Instant ADD/DROP COLUMN leaves row versions behind; after enough runs the table hits
        // "Row size too large". A rebuild resets that.
        $this->connection->executeStatement('ALTER TABLE `act_information_bar` FORCE');
        $this->connection->executeStatement('ALTER TABLE `act_information_bar_translation` FORCE');
    }

    public function testAddsColumns(): void
    {
        $this->dropNewColumns();

        (new Migration1792502181AddCountdownColumns())->update($this->connection);

        $bar = $this->columns('act_information_bar');
        foreach (self::BAR_COLUMNS as $column) {
            self::assertArrayHasKey($column, $bar, "Column {$column} is missing");
            self::assertSame('YES', $bar[$column]['Null'], "Column {$column} must be nullable");
        }
        self::assertStringStartsWith('datetime', $bar['countdown_target']['Type']);

        $translation = $this->columns('act_information_bar_translation');
        self::assertArrayHasKey('countdown_expired_text', $translation);
        self::assertSame('YES', $translation['countdown_expired_text']['Null']);
    }

    public function testExistingBarIsUntouched(): void
    {
        $this->dropNewColumns();

        $id = hex2bin(str_repeat('b', 32));
        $this->connection->delete('act_information_bar', ['id' => $id]);
        $this->connection->insert('act_information_bar', [
            'id' => $id,
            'name' => 'Vorher',
            'active' => 1,
            'text_color' => '#111111',
            'background_color' => '#222222',
            'padding_top' => '4px',
            'font_size' => '14px',
            'button_border_width' => '2px',
            'button_background_color_hover' => '#333333',
            'created_at' => '2026-08-16 00:00:00.000',
        ]);

        try {
            $before = $this->connection->fetchAssociative('SELECT * FROM `act_information_bar` WHERE `id` = :id', ['id' => $id]);

            (new Migration1792502181AddCountdownColumns())->update($this->connection);

            $after = $this->connection->fetchAssociative('SELECT * FROM `act_information_bar` WHERE `id` = :id', ['id' => $id]);

            self::assertIsArray($before);
            self::assertIsArray($after);
            foreach ($before as $column => $value) {
                self::assertSame($value, $after[$column], "Column {$column} changed");
            }
            foreach (self::BAR_COLUMNS as $column) {
                self::assertNull($after[$column], "Column {$column} must be NULL");
            }
        } finally {
            $this->connection->delete('act_information_bar', ['id' => $id]);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function columns(string $table): array
    {
        $result = [];
        foreach ($this->connection->fetchAllAssociative('SHOW COLUMNS FROM `' . $table . '`') as $row) {
            $result[(string) $row['Field']] = $row;
        }

        return $result;
    }

    /**
     * DDL commits implicitly, so the schema is rebuilt to the migrated state by tearDown().
     * Only existing columns are dropped, so a half-migrated database is handled too.
     */
    private function dropNewColumns(): void
    {
        $bar = array_keys($this->columns('act_information_bar'));
        $drop = array_filter(self::BAR_COLUMNS, static fn (string $c): bool => in_array($c, $bar, true));
        if ($drop !== []) {
            $this->connection->executeStatement('ALTER TABLE `act_information_bar` DROP COLUMN ' . implode(', DROP COLUMN ', array_map(static fn (string $c): string => '`' . $c . '`', $drop)));
        }

        if (array_key_exists('countdown_expired_text', $this->columns('act_information_bar_translation'))) {
            $this->connection->executeStatement('ALTER TABLE `act_information_bar_translation` DROP COLUMN `countdown_expired_text`');
        }
    }
}
