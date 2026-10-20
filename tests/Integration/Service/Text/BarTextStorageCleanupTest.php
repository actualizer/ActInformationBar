<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Service\Text;

use Act\InformationBar\Service\Text\BarTextFilter;
use Act\InformationBar\Service\Text\BarTextStorageCleanup;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class BarTextStorageCleanupTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const CLEAN_UPDATED_AT = '2026-01-01 10:00:00.000';

    private string $dirtyId;

    private string $cleanId;

    protected function setUp(): void
    {
        // Runs inside the per-test transaction (rolled back), so foreign rows cannot inflate the counts.
        $this->connection()->executeStatement('DELETE FROM `act_information_bar_translation`');
        $this->dirtyId = Uuid::randomHex();
        $this->cleanId = Uuid::randomHex();
        $this->insertBar($this->dirtyId, "<script>x</script>Hallo\u{202E}", '<b>Los</b>', '');
        $this->insertBar($this->cleanId, 'Hallo <b>Welt</b>', 'Los');
    }

    public function testDirtyRowIsCleanedAndCleanRowUntouched(): void
    {
        $cleanBefore = $this->row($this->cleanId);

        self::assertSame(1, $this->cleanup()->cleanAll());

        self::assertSame('xHallo', $this->row($this->dirtyId)['message']);
        self::assertSame('Los', $this->row($this->dirtyId)['button_text'], 'Plain columns lose their tags');
        self::assertNull($this->row($this->dirtyId)['button_title'], 'An empty string is stored as NULL, like the write subscriber does');
        self::assertSame($cleanBefore, $this->row($this->cleanId));
        self::assertSame(self::CLEAN_UPDATED_AT, $this->row($this->cleanId)['updated_at']);
    }

    public function testSecondRunChangesNothing(): void
    {
        $this->cleanup()->cleanAll();

        self::assertSame(0, $this->cleanup()->cleanAll());
    }

    private function cleanup(): BarTextStorageCleanup
    {
        return new BarTextStorageCleanup($this->connection(), new BarTextFilter());
    }

    private function insertBar(string $id, string $message, string $buttonText, ?string $buttonTitle = null): void
    {
        $this->connection()->insert('act_information_bar', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => 'Bar',
            'created_at' => '2026-01-01 10:00:00.000',
        ]);
        $this->connection()->insert('act_information_bar_translation', [
            'act_information_bar_id' => Uuid::fromHexToBytes($id),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'message' => $message,
            'button_text' => $buttonText,
            'button_title' => $buttonTitle,
            'created_at' => '2026-01-01 10:00:00.000',
            'updated_at' => self::CLEAN_UPDATED_AT,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $id): array
    {
        $row = $this->connection()->fetchAssociative(
            'SELECT * FROM `act_information_bar_translation` WHERE `act_information_bar_id` = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );
        self::assertIsArray($row);

        return $row;
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
