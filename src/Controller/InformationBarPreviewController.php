<?php declare(strict_types=1);

namespace Act\InformationBar\Controller;

use Act\InformationBar\Service\Text\BarTextFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('discovery')]
class InformationBarPreviewController extends AbstractController
{
    public function __construct(private readonly BarTextFilter $textFilter)
    {
    }

    /**
     * Mirrors save and render: stage 1 (storage) first, then stage 2 (output) on its result.
     */
    #[Route(
        path: '/api/_action/act-information-bar/preview-text',
        name: 'api.action.act_information_bar.preview_text',
        methods: ['POST'],
        defaults: ['_acl' => ['act_information_bar:read']]
    )]
    public function previewText(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $message = $payload['message'] ?? null;
        $expiredText = $payload['expiredText'] ?? null;

        $storedMessage = $this->textFilter->sanitizeForStorage(is_string($message) ? $message : '');
        $storedExpired = $this->textFilter->sanitizeForStorage(is_string($expiredText) ? $expiredText : '');

        return new JsonResponse([
            'lines' => $this->textFilter->filterMessage($storedMessage->text)->lines,
            'expiredHtml' => $this->textFilter->filterLine($storedExpired->text)->lines[0] ?? null,
            'removedOnSave' => [...$storedMessage->removedFragments, ...$storedExpired->removedFragments],
            'invisibleRemoved' => $storedMessage->invisibleRemoved + $storedExpired->invisibleRemoved,
            'messageWarnings' => [
                'removedOnSave' => $storedMessage->removedFragments,
                'invisibleRemoved' => $storedMessage->invisibleRemoved,
            ],
            'expiredTextWarnings' => [
                'removedOnSave' => $storedExpired->removedFragments,
                'invisibleRemoved' => $storedExpired->invisibleRemoved,
            ],
        ]);
    }
}
