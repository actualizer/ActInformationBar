<?php declare(strict_types=1);

namespace Act\InformationBar\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Template values copied into a bar when it is created, plus the shop timezone.
 *
 * These keys are deliberately absent from config.xml: they must not appear on the plugin
 * configuration page, where they would read as settings that take effect. Only the
 * timezone is evaluated at runtime; everything else is a copy source.
 */
#[Package('discovery')]
class BarDefaultsProvider
{
    public const CONFIG_PREFIX = 'ActInformationBar.defaults.';

    public const TIMEZONE_KEY = 'ActInformationBar.defaults.timezone';

    /**
     * @var list<string>
     */
    public const STYLING_KEYS = [
        'fullWidth', 'displayDuration', 'showButton', 'buttonTarget',
        'textColor', 'backgroundColor', 'paddingTop', 'paddingBottom', 'fontSize',
        'buttonTextColor', 'buttonTextColorHover',
        'buttonBorderColor', 'buttonBorderColorHover', 'buttonBorderWidth',
        'buttonBackgroundColor', 'buttonBackgroundColorHover',
    ];

    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaults(?string $salesChannelId = null): array
    {
        $values = [];

        foreach (self::STYLING_KEYS as $key) {
            $values[$key] = $this->systemConfigService->get(self::CONFIG_PREFIX . $key, $salesChannelId);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function saveDefaults(array $values, ?string $salesChannelId = null): void
    {
        foreach (self::STYLING_KEYS as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            $this->systemConfigService->set(self::CONFIG_PREFIX . $key, $values[$key], $salesChannelId);
        }
    }

    /**
     * Falls back to the server timezone so an untouched installation keeps behaving
     * exactly as before the setting existed.
     *
     * date_default_timezone_get() is unusable for this: Shopware\Core\Kernel::__construct()
     * unconditionally calls date_default_timezone_set('UTC'), so it never reflects the
     * actual server timezone once the kernel has booted. php.ini's date.timezone and the
     * TZ environment variable are untouched by that call and are used instead.
     */
    public function getTimezone(): string
    {
        $configured = $this->systemConfigService->get(self::TIMEZONE_KEY);

        if (is_string($configured) && $configured !== '' && in_array($configured, timezone_identifiers_list(), true)) {
            return $configured;
        }

        foreach ([ini_get('date.timezone'), getenv('TZ')] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && in_array($candidate, timezone_identifiers_list(), true)) {
                return $candidate;
            }
        }

        return 'UTC';
    }

    public function saveTimezone(string $timezone): void
    {
        $this->systemConfigService->set(self::TIMEZONE_KEY, $timezone);
    }
}
