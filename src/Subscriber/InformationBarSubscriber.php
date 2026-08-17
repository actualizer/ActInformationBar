<?php declare(strict_types=1);

namespace Act\InformationBar\Subscriber;

use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Act\InformationBar\Service\InformationBarTextLoader;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InformationBarSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly InformationBarTextLoader $textLoader)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GenericPageLoadedEvent::class => 'onPageLoaded',
        ];
    }

    public function onPageLoaded(GenericPageLoadedEvent $event): void
    {
        if ($event->getRequest()->isXmlHttpRequest()) {
            return;
        }

        $result = $this->textLoader->load($event->getSalesChannelContext());

        if ($result === null) {
            $event->getPage()->addExtension('actInformationBar', new ArrayStruct(['show' => false]));

            return;
        }

        $bar = $result->bar;

        $event->getPage()->addExtension('actInformationBar', new ArrayStruct([
            'show' => true,
            'message' => $result->text->message ?? '',
            'buttonText' => $result->text->buttonText ?? '',
            'buttonTitle' => $result->text->buttonTitle ?? '',
            'buttonUrl' => $result->text->buttonUrl ?? '',
            'displayDuration' => $bar->getDisplayDuration(),
            'fullWidth' => $bar->getFullWidth(),
            'showButton' => $bar->getShowButton(),
            'buttonTarget' => $bar->getButtonTarget(),
            'style' => $this->buildStyle($bar),
        ]));
    }

    /**
     * Builds the wrapper's inline style attribute from entity values so a colour or
     * spacing change is visible without recompiling the theme.
     */
    private function buildStyle(InformationBarEntity $bar): string
    {
        $declarations = [
            '--act-bar-text' => $bar->getTextColor(),
            '--act-bar-bg' => $bar->getBackgroundColor(),
            '--act-bar-padding-top' => $bar->getPaddingTop(),
            '--act-bar-padding-bottom' => $bar->getPaddingBottom(),
            '--act-bar-font-size' => $bar->getFontSize(),
            '--act-bar-btn-text' => $bar->getButtonTextColor(),
            '--act-bar-btn-text-hover' => $bar->getButtonTextColorHover(),
            '--act-bar-btn-border' => $bar->getButtonBorderColor(),
            '--act-bar-btn-border-hover' => $bar->getButtonBorderColorHover(),
            '--act-bar-btn-border-width' => $bar->getButtonBorderWidth(),
            '--act-bar-btn-bg' => $bar->getButtonBackgroundColor(),
            '--act-bar-btn-bg-hover' => $bar->getButtonBackgroundColorHover(),
        ];

        $output = [];

        foreach ($declarations as $property => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Reject values that could close the declaration list and inject further CSS
            // through the unescaped style attribute (see Twig autoescape note in the template).
            if (str_contains($value, ';') || str_contains($value, '}')) {
                continue;
            }

            // Bare numbers (e.g. a pre-migration "14") need an explicit unit now that the
            // template no longer appends "px" itself.
            if (is_numeric($value)) {
                $value .= 'px';
            }

            $output[] = $property . ': ' . $value;
        }

        return implode('; ', $output);
    }
}
