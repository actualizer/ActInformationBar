<?php declare(strict_types=1);

namespace Act\InformationBar\Subscriber;

use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Act\InformationBar\Service\Countdown\CountdownViewFactory;
use Act\InformationBar\Service\InformationBarCacheTag;
use Act\InformationBar\Service\InformationBarTextLoader;
use Act\InformationBar\Service\Text\BarTextFilter;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class InformationBarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly InformationBarTextLoader $textLoader,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly BarTextFilter $textFilter,
        private readonly CountdownViewFactory $countdownViewFactory,
        private readonly ClockInterface $clock,
    ) {
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

        $this->dispatcher->dispatch(new AddCacheTagEvent(InformationBarCacheTag::TAG));

        $result = $this->textLoader->load($event->getSalesChannelContext());

        if ($result === null) {
            $event->getPage()->addExtension('actInformationBar', new ArrayStruct(['show' => false]));

            return;
        }

        $bar = $result->bar;

        $event->getPage()->addExtension('actInformationBar', new ArrayStruct([
            'show' => true,
            // Kept for themes reading the old key; the own template renders only 'lines'.
            'message' => $result->text->message ?? '',
            'lines' => $this->textFilter->filterMessage($result->text->message ?? '')->lines,
            'buttonText' => $this->textFilter->cleanPlain($result->text->buttonText ?? ''),
            'buttonTitle' => $this->textFilter->cleanPlain($result->text->buttonTitle ?? ''),
            'buttonUrl' => $result->text->buttonUrl ?? '',
            'displayDuration' => $bar->getDisplayDuration(),
            'fullWidth' => $bar->getFullWidth(),
            'showButton' => $bar->getShowButton(),
            'buttonTarget' => $bar->getButtonTarget(),
            'style' => $this->buildStyle($bar),
            'countdown' => $this->countdownViewFactory->create($bar, $result->text, $this->clock->now()),
            'countdownFont' => $bar->getCountdownFont() === 'montserrat' ? 'montserrat' : 'theme',
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
            '--act-bar-btn-radius' => $bar->getButtonBorderRadius(),
            '--act-bar-countdown-color' => $bar->getCountdownColor(),
            '--act-bar-countdown-label-color' => $bar->getCountdownLabelColor(),
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

        // Without an explicit label colour the SCSS default (muted opacity) applies.
        if (array_filter($output, static fn (string $d): bool => str_starts_with($d, '--act-bar-countdown-label-color:')) !== []) {
            $output[] = '--act-bar-countdown-label-opacity: 1';
        }

        return implode('; ', $output);
    }
}
