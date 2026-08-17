<?php declare(strict_types=1);

namespace Act\InformationBar\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1787000100MigrateStylingFromConfig extends MigrationStep
{
    /**
     * Config key => column name.
     */
    private const COLUMN_MAP = [
        'fullWidth' => 'full_width',
        'displayDuration' => 'display_duration',
        'showButton' => 'show_button',
        'buttonTarget' => 'button_target',
        'textColor' => 'text_color',
        'backgroundColor' => 'background_color',
        'paddingTop' => 'padding_top',
        'paddingBottom' => 'padding_bottom',
        'fontSize' => 'font_size',
        'buttonTextColor' => 'button_text_color',
        'buttonTextColorHover' => 'button_text_color_hover',
        'buttonBorderColor' => 'button_border_color',
        'buttonBorderColorHover' => 'button_border_color_hover',
        'buttonBorderWidth' => 'button_border_width',
        'buttonBackgroundColor' => 'button_background_color',
        'buttonBackgroundColorHover' => 'button_background_color_hover',
    ];

    public function getCreationTimestamp(): int
    {
        return 1787000100;
    }

    public function update(Connection $connection): void
    {
        $config = $this->collectConfig($connection);

        foreach ($config as $salesChannelKey => $values) {
            $salesChannelId = $salesChannelKey === '' ? null : $salesChannelKey;

            $this->writeBar($connection, $salesChannelId, $values);
            $this->seedDefaults($connection, $salesChannelId, $values);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function collectConfig(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT `configuration_key`, `configuration_value`, LOWER(HEX(`sales_channel_id`)) AS sales_channel_id
             FROM `system_config`
             WHERE `configuration_key` LIKE "ActInformationBar.config.%"
             ORDER BY `sales_channel_id`'
        );

        $collected = [];

        foreach ($rows as $row) {
            // PHP silently casts a null array key to "", which breaks Uuid::fromHexToBytes later.
            $key = $row['sales_channel_id'] ?? '';
            $name = substr((string) $row['configuration_key'], strlen('ActInformationBar.config.'));

            $decoded = json_decode((string) $row['configuration_value'], true);
            $collected[$key][$name] = is_array($decoded) ? ($decoded['_value'] ?? null) : null;
        }

        return $collected;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function writeBar(Connection $connection, ?string $salesChannelId, array $values): void
    {
        $barId = $connection->fetchOne(
            'SELECT `id` FROM `act_information_bar` WHERE `sales_channel_id` <=> ?',
            [$salesChannelId === null ? null : Uuid::fromHexToBytes($salesChannelId)]
        );

        if ($barId === false) {
            return;
        }

        $set = [];
        $params = [];

        foreach (self::COLUMN_MAP as $configKey => $column) {
            if (!array_key_exists($configKey, $values) || $values[$configKey] === null) {
                continue;
            }

            $set[] = "`{$column}` = ?";
            $params[] = is_bool($values[$configKey]) ? (int) $values[$configKey] : $values[$configKey];
        }

        $set[] = '`name` = ?';
        $params[] = 'Informationsleiste';

        $set[] = '`active` = ?';
        $params[] = (int) (bool) ($values['isActive'] ?? true);

        $set[] = '`start_date` = ?';
        $params[] = $this->toDateTime($values['startDate'] ?? null);

        $set[] = '`end_date` = ?';
        $params[] = $this->toDateTime($values['endDate'] ?? null);

        $params[] = $barId;

        $connection->executeStatement(
            'UPDATE `act_information_bar` SET ' . implode(', ', $set) . ' WHERE `id` = ?',
            $params
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function seedDefaults(Connection $connection, ?string $salesChannelId, array $values): void
    {
        $salesChannelIdBytes = $salesChannelId === null ? null : Uuid::fromHexToBytes($salesChannelId);

        foreach (array_keys(self::COLUMN_MAP) as $configKey) {
            if (!array_key_exists($configKey, $values) || $values[$configKey] === null) {
                continue;
            }

            $configurationKey = 'ActInformationBar.defaults.' . $configKey;
            $configurationValue = json_encode(['_value' => $values[$configKey]], JSON_THROW_ON_ERROR);

            // MySQL never treats two NULLs as equal in a unique index, so ON DUPLICATE KEY
            // UPDATE cannot detect an existing row here and would insert a duplicate instead.
            $existingId = $connection->fetchOne(
                'SELECT `id` FROM `system_config` WHERE `configuration_key` = ? AND `sales_channel_id` <=> ?',
                [$configurationKey, $salesChannelIdBytes]
            );

            if ($existingId !== false) {
                $connection->executeStatement(
                    'UPDATE `system_config` SET `configuration_value` = ? WHERE `id` = ?',
                    [$configurationValue, $existingId]
                );

                continue;
            }

            $connection->executeStatement(
                'INSERT INTO `system_config` (`id`, `configuration_key`, `configuration_value`, `sales_channel_id`, `created_at`)
                 VALUES (?, ?, ?, ?, NOW(3))',
                [Uuid::randomBytes(), $configurationKey, $configurationValue, $salesChannelIdBytes]
            );
        }
    }

    private function toDateTime(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            if ($this->hasExplicitOffset($value)) {
                return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s.v');
            }

            // Offset-less legacy values must be read in the shop timezone, not the ambient
            // one: this migration runs under the Shopware Kernel, which forces PHP's default
            // timezone to UTC on boot, so date_default_timezone_get() would silently mask
            // whatever the server was actually configured for. The resolution order mirrors
            // BarDefaultsProvider::getTimezone(), duplicated here because that service cannot
            // be injected into a migration.
            return (new \DateTimeImmutable($value, $this->resolveAmbientTimezone()))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s.v');
        } catch (\Exception) {
            return null;
        }
    }

    private function hasExplicitOffset(string $value): bool
    {
        return (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $value);
    }

    private function resolveAmbientTimezone(): \DateTimeZone
    {
        foreach ([ini_get('date.timezone'), getenv('TZ')] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && in_array($candidate, timezone_identifiers_list(), true)) {
                return new \DateTimeZone($candidate);
            }
        }

        return new \DateTimeZone('UTC');
    }
}
