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
        $textColor = $this->getStringConfig('textColor', $salesChannelId, '#FFFFFF');
        $backgroundColor = $this->getStringConfig('backgroundColor', $salesChannelId, '#000000');
        $paddingTop = $this->getNumericConfig('paddingTop', $salesChannelId, 15);
        $paddingBottom = $this->getNumericConfig('paddingBottom', $salesChannelId, 15);
        $fontSize = $this->getNumericConfig('fontSize', $salesChannelId, 14);

        // Button styling variables
        $buttonTextColor = $this->getStringConfig('buttonTextColor', $salesChannelId, '#FFFFFF');
        $buttonTextColorHover = $this->getStringConfig('buttonTextColorHover', $salesChannelId, '#FFFFFF');
        $buttonBorderColor = $this->getStringConfig('buttonBorderColor', $salesChannelId, '#FFFFFF');
        $buttonBorderColorHover = $this->getStringConfig('buttonBorderColorHover', $salesChannelId, '#FFFFFF');
        $buttonBorderWidth = $this->getNumericConfig('buttonBorderWidth', $salesChannelId, 1);
        $buttonBackgroundColor = $this->getStringConfig('buttonBackgroundColor', $salesChannelId, 'transparent');
        $buttonBackgroundColorHover = $this->getStringConfig('buttonBackgroundColorHover', $salesChannelId, 'transparent');

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

    /**
     * SystemConfigService::get() is broadly typed (array|bool|float|int|string|null).
     * Fall back to the default unless the stored value is actually a string, so the
     * value passed into the SCSS compiler is always a valid string.
     */
    private function getStringConfig(string $key, ?string $salesChannelId, string $default): string
    {
        $value = $this->systemConfigService->get('ActInformationBar.config.' . $key, $salesChannelId);

        return is_string($value) ? $value : $default;
    }

    /**
     * Same broad-type guard for numeric variables; returns a string so it can be
     * concatenated with the "px" unit suffix.
     */
    private function getNumericConfig(string $key, ?string $salesChannelId, int $default): string
    {
        $value = $this->systemConfigService->get('ActInformationBar.config.' . $key, $salesChannelId);

        return is_numeric($value) ? (string) $value : (string) $default;
    }
}
