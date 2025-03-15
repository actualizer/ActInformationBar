<?php declare(strict_types=1);

namespace Act\InformationBar\Subscriber;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\GenericPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\Struct\ArrayStruct;


class InformationBarSubscriber implements EventSubscriberInterface
{
    private SystemConfigService $systemConfigService;

    /**
     * TopBarSubscriber constructor.
     *
     * @param SystemConfigService $systemConfigService The system config service.
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to.
     */
    public static function getSubscribedEvents()
    {
        return [
            GenericPageLoadedEvent::class => 'onPageLoaded'
        ];
    }

    /**
     * Handler for the GenericPageLoadedEvent.
     *
     * @param GenericPageLoadedEvent $event The event instance.
     * @return void
     * @throws \Exception
     */
    public function onPageLoaded(GenericPageLoadedEvent $event): void
    {
        // Check if the request is an XMLHttpRequest (AJAX request).
        if ($event->getRequest()->isXmlHttpRequest()) {
            return;
        }

        // Get all configuration values under the 'ActInformationBar.config' key.
        // Note: The order of the variables must match the order of the fields in the config.xml file.
        // If you reorder the fields in config.xml, you must also reorder these variables.
        $config = $this->systemConfigService->get('ActInformationBar.config');

        $isActive = $config['isActive'] ?? true;
        $fullWidth = $config['fullWidth'] ?? false;
        $message = $config['message'] ?? '';
        $displayDuration = $config['displayDuration'] ?? 3;
        $startDate = $config['startDate'] ?? '';
        $endDate = $config['endDate'] ?? '';
        $textColor = $config['textColor'] ?? '';
        $backgroundColor = $config['backgroundColor'] ?? '';
        $paddingTop = $config['paddingTop'] ?? 15;
        $paddingBottom = $config['paddingBottom'] ?? 15;
        $fontSize = $config['fontSize'] ?? 14;
        $showButton = $config['showButton'] ?? false;
        $buttonText = $config['buttonText'] ?? '';
        $buttonUrl = $config['buttonUrl'] ?? '';
        $buttonTarget = $config['buttonTarget'] ?? '_self';
        $buttonTitle = $config['buttonTitle'] ?? '';
        $buttonTextColor = $config['buttonTextColor'] ?? '';
        $buttonTextColorHover = $config['buttonTextColorHover'] ?? '';
        $buttonBorderColor = $config['buttonBorderColor'] ?? '';
        $buttonBorderColorHover = $config['buttonBorderColorHover'] ?? '';
        $buttonBorderWidth = $config['buttonBorderWidth'] ?? 1;
        $buttonBackgroundColor = $config['buttonBackgroundColor'] ?? '';
        $buttonBackgroundColorHover = $config['buttonBackgroundColorHover'] ?? '';



        // Create a DateTime object for the current date and time
        $now = new \DateTime();
        // Create DateTime objects for the start and end dates from the configuration, if provided
        $start = empty($startDate) ? null : new \DateTime($startDate);
        $end = empty($endDate) ? null : new \DateTime($endDate);

        // Check if the current date is between the start and end dates (if provided) and if the bar is active
        $show = $isActive && (
                ($start === null && $end === null) ||
                ($start === null && $now <= $end) ||
                ($end === null && $now >= $start) ||
                ($start !== null && $end !== null && $now >= $start && $now <= $end)
            );

        // Create an ArrayStruct to hold the top bar data
        $informationBarData = new ArrayStruct([
            'show' => $show,
            'message' => $message,
            'textColor' => $textColor,
            'backgroundColor' => $backgroundColor,
            'paddingTop' => $paddingTop,
            'paddingBottom' => $paddingBottom,
            'fontSize' => $fontSize,
            'fullWidth' => $fullWidth,
            'showButton' => $showButton,
            'buttonText' => $buttonText,
            'buttonUrl' => $buttonUrl,
            'buttonTarget' => $buttonTarget,
            'buttonTitle' => $buttonTitle,
            'buttonTextColor' => $buttonTextColor,
            'buttonTextColorHover' => $buttonTextColorHover,
            'buttonBorderColor' => $buttonBorderColor,
            'buttonBorderColorHover' => $buttonBorderColorHover,
            'buttonBorderWidth' => $buttonBorderWidth,
            'buttonBackgroundColor' => $buttonBackgroundColor,
            'buttonBackgroundColorHover' => $buttonBackgroundColorHover,
            'displayDuration' => $displayDuration,
        ]);

        $event->getPage()->getHeader()->addExtension('actInformationBar', $informationBarData);

    }

}
