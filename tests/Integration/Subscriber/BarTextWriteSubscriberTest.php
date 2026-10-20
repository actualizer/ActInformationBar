<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class BarTextWriteSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const DIRTY_MESSAGE = "Ich <3 euch <b>x</b><br><!-- y -->\u{200B}";

    public function testRepositoryWriteIsSanitized(): void
    {
        $id = Uuid::randomHex();

        $this->repository()->create([$this->barPayload($id)], Context::createCLIContext());

        $this->assertStoredSanitized($id);
    }

    public function testSyncApiWriteIsSanitized(): void
    {
        $id = Uuid::randomHex();

        $request = Request::create('/api/_action/sync', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([[
            'key' => 'write',
            'action' => 'upsert',
            'entity' => 'act_information_bar',
            'payload' => [$this->barPayload($id)],
        ]], \JSON_THROW_ON_ERROR));
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setIsAdmin(true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, new Context($source));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, true);

        $response = KernelLifecycleManager::getKernel()->handle($request);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $this->assertStoredSanitized($id);
    }

    public function testUpdateOfOtherFieldsLeavesTextAlone(): void
    {
        $id = Uuid::randomHex();
        $this->repository()->create([[
            'id' => $id,
            'name' => 'Bar',
            'translations' => [Defaults::LANGUAGE_SYSTEM => ['message' => 'ok']],
        ]], Context::createCLIContext());

        // Planted directly: only a subscriber that touches `message` on this update could change it.
        $dirty = "<script>x</script>\u{200B}";
        $this->connection()->update('act_information_bar_translation', ['message' => $dirty], ['act_information_bar_id' => Uuid::fromHexToBytes($id)]);

        $this->repository()->update([['id' => $id, 'textColor' => '#123456']], Context::createCLIContext());

        self::assertSame($dirty, $this->stored($id, 'message'));
    }

    public function testTextThatBecomesEmptyIsStoredAsNull(): void
    {
        $id = Uuid::randomHex();
        $this->repository()->create([[
            'id' => $id,
            'name' => 'Bar',
            'translations' => [Defaults::LANGUAGE_SYSTEM => [
                'message' => '<img src=x>',
                'countdownExpiredText' => '<script></script>',
                'buttonTitle' => "\u{200B}",
            ]],
        ]], Context::createCLIContext());

        self::assertNull($this->stored($id, 'message'));
        self::assertNull($this->stored($id, 'countdown_expired_text'));
        self::assertNull($this->stored($id, 'button_title'));
    }

    /**
     * @return array<string, mixed>
     */
    private function barPayload(string $id): array
    {
        return [
            'id' => $id,
            'name' => 'Bar',
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'message' => self::DIRTY_MESSAGE,
                    'countdownExpiredText' => '<mark>Jetzt</mark> live<script>',
                    'buttonText' => "Los\u{200B}",
                    'buttonTitle' => "Titel\u{200B}",
                ],
            ],
        ];
    }

    private function assertStoredSanitized(string $id): void
    {
        self::assertSame('Ich <3 euch <b>x</b>', $this->stored($id, 'message'));
        self::assertSame('<mark>Jetzt</mark> live', $this->stored($id, 'countdown_expired_text'));
        self::assertSame('Los', $this->stored($id, 'button_text'));
        self::assertSame('Titel', $this->stored($id, 'button_title'));
    }

    private function stored(string $id, string $column): ?string
    {
        $row = $this->connection()->fetchAssociative(
            'SELECT `' . $column . '` FROM `act_information_bar_translation` WHERE `act_information_bar_id` = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        self::assertIsArray($row, 'Translation row is missing');
        self::assertArrayHasKey($column, $row);

        return $row[$column];
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function repository(): EntityRepository
    {
        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('act_information_bar.repository');

        return $repository;
    }
}
