<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Integration\Controller;

use Act\InformationBar\Service\BarDefaultsProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InformationBarDefaultsControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const ROUTE = '/api/_action/act-information-bar/defaults';

    /**
     * date_default_timezone_get() cannot serve as the expected value here: Shopware's
     * Kernel unconditionally forces it to UTC on boot, which is exactly the bug this test
     * guards against. The expected value is derived from php.ini's date.timezone instead,
     * the same source BarDefaultsProvider::getTimezone() falls back to.
     */
    public function testTimezoneFallsBackToServerTimezone(): void
    {
        $provider = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(BarDefaultsProvider::class);

        $expected = ini_get('date.timezone');

        self::assertNotSame('UTC', $expected, 'This test needs a non-UTC server timezone configured to be meaningful');
        self::assertSame($expected, $provider->getTimezone());
        self::assertNotSame('UTC', $provider->getTimezone(), 'getTimezone() must not silently fall back to UTC when the server has its own timezone');
    }

    public function testInvalidTimezoneIsRejectedAndFallsBack(): void
    {
        $provider = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(BarDefaultsProvider::class);

        $provider->saveTimezone('Not/AZone');

        self::assertSame(ini_get('date.timezone'), $provider->getTimezone());

        $provider->saveTimezone('');
    }

    public function testValidTimezoneIsReturned(): void
    {
        $provider = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(BarDefaultsProvider::class);

        $provider->saveTimezone('Europe/Berlin');

        self::assertSame('Europe/Berlin', $provider->getTimezone());

        $provider->saveTimezone('');
    }

    /**
     * Proves the route is actually reachable over HTTP (i.e. routes.xml wires the
     * attribute-routed controller into the router) and that the JSON payload has the
     * shape the admin service expects: a "defaults" map containing every styling key,
     * plus a "timezone" string.
     *
     * Dispatches through Kernel::handle() using PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED
     * (the same bypass ApiAuthenticationListener grants an already-resolved OAuth context)
     * instead of minting a real bearer token: this project's test-environment JWT signer
     * key is shorter than the 256 bits league/oauth2-server requires (verified directly
     * against /api/oauth/token, which 500s with "Key provided is shorter than 256 bits,
     * only 104 bits provided" — a pre-existing environment issue, not something this
     * task's scope covers). Kernel::handle() still runs the full routing + RouteScope +
     * AclAnnotationValidator + controller + response pipeline against the Context set on
     * the request, so ACL enforcement and everything downstream of it is still exercised
     * exactly as a real HTTP request would.
     */
    public function testLoadRouteReturnsDefaultsAndTimezoneOverHttp(): void
    {
        $response = $this->dispatch('GET', self::ROUTE, context: $this->adminContext());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('defaults', $data);
        self::assertArrayHasKey('timezone', $data);
        self::assertIsArray($data['defaults']);

        foreach (BarDefaultsProvider::STYLING_KEYS as $key) {
            self::assertArrayHasKey($key, $data['defaults'], "Missing styling key \"{$key}\" in the load response.");
        }
    }

    /**
     * Proves a POST over HTTP actually reaches BarDefaultsProvider::saveDefaults()/
     * saveTimezone() (not just that the controller accepts the request), by reading the
     * persisted value back through the service directly.
     */
    public function testSaveRoutePersistsDefaultsAndTimezoneOverHttp(): void
    {
        $response = $this->dispatch('POST', self::ROUTE, [
            'defaults' => ['textColor' => '#abcdef'],
            'timezone' => 'Europe/Berlin',
            'salesChannelId' => null,
        ], $this->adminContext());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['success' => true], json_decode((string) $response->getContent(), true));

        $provider = KernelLifecycleManager::getKernel()->getContainer()->get(BarDefaultsProvider::class);
        self::assertSame('#abcdef', $provider->getDefaults(null)['textColor']);
        self::assertSame('Europe/Berlin', $provider->getTimezone());

        $provider->saveTimezone('');
    }

    /**
     * Proves the is_array($defaults) guard in the controller's save() action is live
     * over HTTP, not just present in source: a string payload must be rejected with 400,
     * not 500 or a silently ignored write.
     */
    public function testSaveRouteRejectsNonArrayDefaultsOverHttp(): void
    {
        $response = $this->dispatch('POST', self::ROUTE, ['defaults' => 'not-an-array'], $this->adminContext());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * Proves the route's "_acl" default is enforced by the framework, not decorative: a
     * context without act_information_bar:read must be denied even though the route exists.
     */
    public function testLoadRouteDeniesWithoutReadPermissionOverHttp(): void
    {
        $response = $this->dispatch('GET', self::ROUTE, context: $this->restrictedContext(['some_other_permission:read']));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatch(string $method, string $uri, array $body = [], ?Context $context = null): Response
    {
        $server = [];
        $content = null;

        if ($method !== 'GET') {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($body, \JSON_THROW_ON_ERROR);
        }

        $request = Request::create($uri, $method, [], [], [], $server, $content);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context ?? $this->adminContext());
        // Bypasses ApiAuthenticationListener's bearer-token check the same way an already
        // resolved OAuth context does internally; ACL enforcement (AclAnnotationValidator)
        // still runs against the Context set above, so ACL checks stay meaningful.
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, true);

        return KernelLifecycleManager::getKernel()->handle($request);
    }

    private function adminContext(): Context
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setIsAdmin(true);

        return new Context($source);
    }

    /**
     * @param list<string> $permissions
     */
    private function restrictedContext(array $permissions): Context
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions($permissions);

        return new Context($source);
    }
}
