<?php declare(strict_types=1);

namespace Act\InformationBar\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1786000100MigrateTextsFromConfig extends MigrationStep
{
    private const KEYS = [
        'message' => 'message',
        'buttonText' => 'button_text',
        'buttonTitle' => 'button_title',
        'buttonUrl' => 'button_url',
    ];

    public function getCreationTimestamp(): int
    {
        return 1786000100;
    }

    public function update(Connection $connection): void
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s.v');

        foreach ($this->collectConfiguredTexts($connection) as $salesChannelId => $texts) {
            // PHP casts a null array key to "" - restore null for the global (non-sales-channel) entry.
            $salesChannelId = $salesChannelId === '' ? null : $salesChannelId;

            // Skip sales channels that never had any text configured.
            if ($this->isEmpty($texts)) {
                continue;
            }

            $barId = $this->findOrCreateBar($connection, $salesChannelId, $now);
            $languageId = $this->resolveLanguageId($connection, $salesChannelId);

            $this->insertTranslation($connection, $barId, $languageId, $texts, $now);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return array<string|null, array<string, string|null>> keyed by hex sales channel id, null key = global value
     */
    private function collectConfiguredTexts(Connection $connection): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT `configuration_key`, `configuration_value`, LOWER(HEX(`sales_channel_id`)) AS sales_channel_id
             FROM `system_config`
             WHERE `configuration_key` IN (:keys)',
            ['keys' => array_map(
                static fn (string $key): string => 'ActInformationBar.config.' . $key,
                array_keys(self::KEYS)
            )],
            ['keys' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $collected = [];

        foreach ($rows as $row) {
            $key = substr((string) $row['configuration_key'], \strlen('ActInformationBar.config.'));
            $decoded = json_decode((string) $row['configuration_value'], true);
            $value = \is_array($decoded) && \array_key_exists('_value', $decoded) ? $decoded['_value'] : null;

            if (!\is_string($value) || $value === '') {
                continue;
            }

            $collected[$row['sales_channel_id']][$key] = $value;
        }

        return $collected;
    }

    /**
     * @param array<string, string|null> $texts
     */
    private function isEmpty(array $texts): bool
    {
        foreach ($texts as $value) {
            if (\is_string($value) && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function findOrCreateBar(Connection $connection, ?string $salesChannelIdHex, string $now): string
    {
        $existing = $salesChannelIdHex === null
            ? $connection->fetchOne('SELECT `id` FROM `act_information_bar` WHERE `sales_channel_id` IS NULL')
            : $connection->fetchOne(
                'SELECT `id` FROM `act_information_bar` WHERE `sales_channel_id` = :id',
                ['id' => Uuid::fromHexToBytes($salesChannelIdHex)]
            );

        if (\is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = Uuid::randomBytes();

        $connection->insert('act_information_bar', [
            'id' => $id,
            'sales_channel_id' => $salesChannelIdHex === null ? null : Uuid::fromHexToBytes($salesChannelIdHex),
            'created_at' => $now,
        ]);

        return $id;
    }

    private function resolveLanguageId(Connection $connection, ?string $salesChannelIdHex): string
    {
        if ($salesChannelIdHex === null) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        $languageId = $connection->fetchOne(
            'SELECT `language_id` FROM `sales_channel` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($salesChannelIdHex)]
        );

        return \is_string($languageId) && $languageId !== ''
            ? $languageId
            : Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
    }

    /**
     * @param array<string, string|null> $texts
     */
    private function insertTranslation(Connection $connection, string $barId, string $languageId, array $texts, string $now): void
    {
        $data = [
            'act_information_bar_id' => $barId,
            'language_id' => $languageId,
            'created_at' => $now,
        ];

        foreach (self::KEYS as $configKey => $column) {
            $data[$column] = $texts[$configKey] ?? null;
        }

        $connection->executeStatement(
            'INSERT IGNORE INTO `act_information_bar_translation`
                (`act_information_bar_id`, `language_id`, `message`, `button_text`, `button_title`, `button_url`, `created_at`)
             VALUES (:act_information_bar_id, :language_id, :message, :button_text, :button_title, :button_url, :created_at)',
            $data
        );
    }
}
