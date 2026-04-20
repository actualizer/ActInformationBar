<?php declare(strict_types=1);

namespace Act\InformationBar\Subscriber;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ScssVariablesSubscriber implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ThemeCompilerEnrichScssVariablesEvent::class => 'onAddScssVariables'
        ];
    }

    /**
     * @param ThemeCompilerEnrichScssVariablesEvent $event
     */
    public function onAddScssVariables(ThemeCompilerEnrichScssVariablesEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelId();

        // Information bar styling variables
        $textColor = $this->systemConfigService->get('ActInformationBar.config.textColor', $salesChannelId) ?? '#FFFFFF';
        $backgroundColor = $this->systemConfigService->get('ActInformationBar.config.backgroundColor', $salesChannelId) ?? '#000000';
        $paddingTop = $this->systemConfigService->get('ActInformationBar.config.paddingTop', $salesChannelId) ?? 15;
        $paddingBottom = $this->systemConfigService->get('ActInformationBar.config.paddingBottom', $salesChannelId) ?? 15;
        $fontSize = $this->systemConfigService->get('ActInformationBar.config.fontSize', $salesChannelId) ?? 14;

        // Button styling variables
        $buttonTextColor = $this->systemConfigService->get('ActInformationBar.config.buttonTextColor', $salesChannelId) ?? '#FFFFFF';
        $buttonTextColorHover = $this->systemConfigService->get('ActInformationBar.config.buttonTextColorHover', $salesChannelId) ?? '#FFFFFF';
        $buttonBorderColor = $this->systemConfigService->get('ActInformationBar.config.buttonBorderColor', $salesChannelId) ?? '#FFFFFF';
        $buttonBorderColorHover = $this->systemConfigService->get('ActInformationBar.config.buttonBorderColorHover', $salesChannelId) ?? '#FFFFFF';
        $buttonBorderWidth = $this->systemConfigService->get('ActInformationBar.config.buttonBorderWidth', $salesChannelId) ?? 1;
        $buttonBackgroundColor = $this->systemConfigService->get('ActInformationBar.config.buttonBackgroundColor', $salesChannelId) ?? 'transparent';
        $buttonBackgroundColorHover = $this->systemConfigService->get('ActInformationBar.config.buttonBackgroundColorHover', $salesChannelId) ?? 'transparent';

        // Add information bar variables to SCSS compilation
        $event->addVariable('act-information-bar-text-color', $textColor);
        $event->addVariable('act-information-bar-background-color', $backgroundColor);
        $event->addVariable('act-information-bar-padding-top', $paddingTop . 'px');
        $event->addVariable('act-information-bar-padding-bottom', $paddingBottom . 'px');
        $event->addVariable('act-information-bar-font-size', $fontSize . 'px');
        
        // Add button styling variables to SCSS compilation
        $event->addVariable('act-information-bar-button-text-color', $buttonTextColor);
        $event->addVariable('act-information-bar-button-text-color-hover', $buttonTextColorHover);
        $event->addVariable('act-information-bar-button-border-color', $buttonBorderColor);
        $event->addVariable('act-information-bar-button-border-color-hover', $buttonBorderColorHover);
        $event->addVariable('act-information-bar-button-border-width', $buttonBorderWidth . 'px');
        $event->addVariable('act-information-bar-button-background-color', $buttonBackgroundColor);
        $event->addVariable('act-information-bar-button-background-color-hover', $buttonBackgroundColorHover);
    }
}
