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
        // Information bar styling variables
        $textColor = $this->systemConfigService->get('ActInformationBar.config.textColor') ?? '#FFFFFF';
        $backgroundColor = $this->systemConfigService->get('ActInformationBar.config.backgroundColor') ?? '#000000';
        $paddingTop = $this->systemConfigService->get('ActInformationBar.config.paddingTop') ?? 15;
        $paddingBottom = $this->systemConfigService->get('ActInformationBar.config.paddingBottom') ?? 15;
        $fontSize = $this->systemConfigService->get('ActInformationBar.config.fontSize') ?? 14;
        
        // Button styling variables
        $buttonTextColor = $this->systemConfigService->get('ActInformationBar.config.buttonTextColor') ?? '#FFFFFF';
        $buttonTextColorHover = $this->systemConfigService->get('ActInformationBar.config.buttonTextColorHover') ?? '#FFFFFF';
        $buttonBorderColor = $this->systemConfigService->get('ActInformationBar.config.buttonBorderColor') ?? '#FFFFFF';
        $buttonBorderColorHover = $this->systemConfigService->get('ActInformationBar.config.buttonBorderColorHover') ?? '#FFFFFF';
        $buttonBorderWidth = $this->systemConfigService->get('ActInformationBar.config.buttonBorderWidth') ?? 1;
        $buttonBackgroundColor = $this->systemConfigService->get('ActInformationBar.config.buttonBackgroundColor') ?? 'transparent';
        $buttonBackgroundColorHover = $this->systemConfigService->get('ActInformationBar.config.buttonBackgroundColorHover') ?? 'transparent';

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
