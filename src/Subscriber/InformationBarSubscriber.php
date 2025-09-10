<?php declare(strict_types=1);

namespace Act\InformationBar\Subscriber;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\Struct\ArrayStruct;

class InformationBarSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GenericPageLoadedEvent::class => 'onPageLoaded'
        ];
    }

    public function onPageLoaded(GenericPageLoadedEvent $event): void
    {
        if ($this->isAjaxRequest($event)) {
            return;
        }

        $config = $this->getConfigValues();
        $now = new \DateTime();
        $start = $this->getDateTime($config['startDate']);
        $end = $this->getDateTime($config['endDate']);

        $show = $this->shouldShowBar($config['isActive'], $now, $start, $end);

        $informationBarData = new ArrayStruct(array_merge($config, ['show' => $show]));
        $event->getPage()->addExtension('actInformationBar', $informationBarData);
    }

    private function isAjaxRequest(GenericPageLoadedEvent $event): bool
    {
        return $event->getRequest()->isXmlHttpRequest();
    }

    private function getConfigValues(): array
    {
        $config = $this->systemConfigService->get('ActInformationBar.config') ?? [];

        return [
            'isActive' => $config['isActive'] ?? true,
            'fullWidth' => $config['fullWidth'] ?? false,
            'message' => $config['message'] ?? '',
            'displayDuration' => $config['displayDuration'] ?? 3,
            'startDate' => $config['startDate'] ?? '',
            'endDate' => $config['endDate'] ?? '',
            'textColor' => $config['textColor'] ?? '',
            'backgroundColor' => $config['backgroundColor'] ?? '',
            'paddingTop' => $config['paddingTop'] ?? 15,
            'paddingBottom' => $config['paddingBottom'] ?? 15,
            'fontSize' => $config['fontSize'] ?? 14,
            'showButton' => $config['showButton'] ?? false,
            'buttonText' => $config['buttonText'] ?? '',
            'buttonUrl' => $config['buttonUrl'] ?? '',
            'buttonTarget' => $config['buttonTarget'] ?? '_self',
            'buttonTitle' => $config['buttonTitle'] ?? '',
            'buttonTextColor' => $config['buttonTextColor'] ?? '',
            'buttonTextColorHover' => $config['buttonTextColorHover'] ?? '',
            'buttonBorderColor' => $config['buttonBorderColor'] ?? '',
            'buttonBorderColorHover' => $config['buttonBorderColorHover'] ?? '',
            'buttonBorderWidth' => $config['buttonBorderWidth'] ?? 1,
            'buttonBackgroundColor' => $config['buttonBackgroundColor'] ?? '',
            'buttonBackgroundColorHover' => $config['buttonBackgroundColorHover'] ?? '',
        ];
    }

    private function getDateTime(?string $date): ?\DateTime
    {
        try {
            return empty($date) ? null : new \DateTime($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function shouldShowBar(bool $isActive, \DateTime $now, ?\DateTime $start, ?\DateTime $end): bool
    {
        return $isActive && (
            ($start === null && $end === null) ||
            ($start === null && $now <= $end) ||
            ($end === null && $now >= $start) ||
            ($start !== null && $end !== null && $now >= $start && $now <= $end)
        );
    }
}
