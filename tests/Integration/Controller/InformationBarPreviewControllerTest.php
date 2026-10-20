<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Controller;

use Act\InformationBar\Service\Text\BarTextFilter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InformationBarPreviewControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const ROUTE = '/api/_action/act-information-bar/preview-text';

    public function testReturnsWhatTheStorefrontWillShowAfterSaving(): void
    {
        $message = "<b>Sale</b> <script>alert(1)</script>\r\n<i>two</i> & <u>x";
        $expired = '<em>Over</em><br>';
        $filter = new BarTextFilter();

        $data = $this->preview(['message' => $message, 'expiredText' => $expired]);

        self::assertSame(
            $filter->filterMessage($filter->sanitizeForStorage($message)->text)->lines,
            $data['lines']
        );
        self::assertNotSame([], $data['lines']);
        self::assertSame(
            $filter->filterLine($filter->sanitizeForStorage($expired)->text)->lines[0] ?? null,
            $data['expiredHtml']
        );
        self::assertNotNull($data['expiredHtml']);
    }

    public function testReportsWarnings(): void
    {
        $data = $this->preview(['message' => "<br>\u{200B}x<!-- y -->", 'expiredText' => null]);

        self::assertSame(['<br>', '<!-- y -->'], $data['removedOnSave']);
        self::assertSame(1, $data['invisibleRemoved']);
        self::assertNull($data['expiredHtml']);
    }

    public function testWarningsSumMessageAndExpiredText(): void
    {
        $data = $this->preview(['message' => "<br>a\u{200B}", 'expiredText' => "<!-- z -->\u{200B}\u{200B}b"]);

        self::assertSame(['<br>', '<!-- z -->'], $data['removedOnSave']);
        self::assertSame(3, $data['invisibleRemoved']);
    }

    public function testWarningsAreAlsoReportedPerField(): void
    {
        $data = $this->preview(['message' => "<br>a\u{200B}", 'expiredText' => "<!-- z -->\u{200B}\u{200B}b"]);

        self::assertSame(['removedOnSave' => ['<br>'], 'invisibleRemoved' => 1], $data['messageWarnings']);
        self::assertSame(['removedOnSave' => ['<!-- z -->'], 'invisibleRemoved' => 2], $data['expiredTextWarnings']);
    }

    public function testMissingTextsHaveEmptyPerFieldWarnings(): void
    {
        $data = $this->preview([]);

        self::assertSame(['removedOnSave' => [], 'invisibleRemoved' => 0], $data['messageWarnings']);
        self::assertSame(['removedOnSave' => [], 'invisibleRemoved' => 0], $data['expiredTextWarnings']);
    }

    public function testMissingMessageIsAnEmptyPreview(): void
    {
        $data = $this->preview([]);

        self::assertSame([], $data['lines']);
        self::assertNull($data['expiredHtml']);
        self::assertSame([], $data['removedOnSave']);
        self::assertSame(0, $data['invisibleRemoved']);
    }

    public function testNonStringValuesAreTreatedAsEmpty(): void
    {
        $data = $this->preview(['message' => ['x'], 'expiredText' => 5]);

        self::assertSame([], $data['lines']);
        self::assertNull($data['expiredHtml']);
    }

    public function testRequiresReadPrivilege(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['some_other_permission:read']);

        $response = $this->dispatch(['message' => 'x'], new Context($source));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function preview(array $body): array
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setIsAdmin(true);

        $response = $this->dispatch($body, new Context($source));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatch(array $body, Context $context): Response
    {
        $request = Request::create(
            self::ROUTE,
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body === [] ? new \stdClass() : $body, \JSON_THROW_ON_ERROR)
        );
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
        // Same pre-authenticated bypass as InformationBarDefaultsControllerTest; ACL still runs.
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, true);

        return KernelLifecycleManager::getKernel()->handle($request);
    }
}
