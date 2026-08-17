<?php declare(strict_types=1);

namespace Act\InformationBar\Controller;

use Act\InformationBar\Service\BarDefaultsProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('discovery')]
class InformationBarDefaultsController extends AbstractController
{
    public function __construct(private readonly BarDefaultsProvider $defaultsProvider)
    {
    }

    #[Route(
        path: '/api/_action/act-information-bar/defaults',
        name: 'api.action.act_information_bar.defaults.load',
        methods: ['GET'],
        defaults: ['_acl' => ['act_information_bar:read']]
    )]
    public function load(Request $request): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');

        return new JsonResponse([
            'defaults' => $this->defaultsProvider->getDefaults(is_string($salesChannelId) && $salesChannelId !== '' ? $salesChannelId : null),
            'timezone' => $this->defaultsProvider->getTimezone(),
        ]);
    }

    #[Route(
        path: '/api/_action/act-information-bar/defaults',
        name: 'api.action.act_information_bar.defaults.save',
        methods: ['POST'],
        defaults: ['_acl' => ['act_information_bar:update']]
    )]
    public function save(Request $request): JsonResponse
    {
        $payload = $request->toArray();

        $salesChannelId = $payload['salesChannelId'] ?? null;
        $defaults = $payload['defaults'] ?? [];

        if (!is_array($defaults)) {
            throw RoutingException::invalidRequestParameter('defaults');
        }

        $this->defaultsProvider->saveDefaults(
            $defaults,
            is_string($salesChannelId) && $salesChannelId !== '' ? $salesChannelId : null
        );

        if (isset($payload['timezone']) && is_string($payload['timezone'])) {
            $this->defaultsProvider->saveTimezone($payload['timezone']);
        }

        return new JsonResponse(['success' => true]);
    }
}
