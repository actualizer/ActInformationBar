<?php declare(strict_types=1);

namespace Act\InformationBar\Service\Text;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class BarTextStorageCleanup
{
    public function __construct(
        private readonly Connection $connection,
        private readonly BarTextFilter $filter,
    ) {
    }

    /**
     * Idempotent: rows whose values are already clean are not written, so `updated_at` stays.
     *
     * @return int Number of rows changed
     */
    public function cleanAll(): int
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `act_information_bar_id`, `language_id`, `message`, `countdown_expired_text`, `button_text`, `button_title`
             FROM `act_information_bar_translation`'
        );

        $changed = 0;

        foreach ($rows as $row) {
            $update = [];

            foreach (array_keys(BarTextFilter::STORAGE_COLUMNS) as $column) {
                $value = $row[$column] ?? null;
                if (!is_string($value)) {
                    continue;
                }

                $clean = $this->filter->cleanStorageColumn($column, $value);

                if ($clean !== $value) {
                    $update[$column] = $clean;
                }
            }

            if ($update === []) {
                continue;
            }

            $this->connection->update('act_information_bar_translation', $update, [
                'act_information_bar_id' => $row['act_information_bar_id'],
                'language_id' => $row['language_id'],
            ]);
            ++$changed;
        }

        return $changed;
    }
}
