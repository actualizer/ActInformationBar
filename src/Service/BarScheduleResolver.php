<?php declare(strict_types=1);

namespace Act\InformationBar\Service;

use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Orders the bars that may be shown right now, most specific first.
 *
 * Returns a list rather than a single bar on purpose: the caller still has to find a
 * usable translation, and must be able to fall through to the next candidate when the
 * preferred bar has none. Collapsing this to one bar reintroduces the defect fixed in
 * 1.4.0, where a sales-channel bar without a translation hid the bar entirely.
 */
#[Package('discovery')]
class BarScheduleResolver
{
    /**
     * @param list<InformationBarEntity> $bars
     * @return list<InformationBarEntity>
     */
    public function pickCandidates(array $bars, ?string $salesChannelId, \DateTimeInterface $now): array
    {
        $active = array_values(array_filter($bars, static fn (InformationBarEntity $bar): bool => $bar->getActive()));

        $ofChannel = array_values(array_filter(
            $active,
            static fn (InformationBarEntity $bar): bool => $bar->getSalesChannelId() === $salesChannelId
        ));

        $global = array_values(array_filter(
            $active,
            static fn (InformationBarEntity $bar): bool => $bar->getSalesChannelId() === null
        ));

        // A null sales channel context must not list the global bars twice.
        if ($salesChannelId === null) {
            $global = [];
        }

        return array_merge(
            $this->orderGroup($ofChannel, $now),
            $this->orderGroup($global, $now)
        );
    }

    /**
     * Within one group: bars whose window covers now come first, latest start date winning,
     * followed by the evergreen bar without any window.
     *
     * @param list<InformationBarEntity> $bars
     * @return list<InformationBarEntity>
     */
    private function orderGroup(array $bars, \DateTimeInterface $now): array
    {
        $scheduled = [];
        $evergreen = [];

        foreach ($bars as $bar) {
            if ($bar->getStartDate() === null && $bar->getEndDate() === null) {
                $evergreen[] = $bar;

                continue;
            }

            if ($this->covers($bar, $now)) {
                $scheduled[] = $bar;
            }
        }

        usort($scheduled, static function (InformationBarEntity $a, InformationBarEntity $b): int {
            $aStart = $a->getStartDate()?->getTimestamp() ?? PHP_INT_MIN;
            $bStart = $b->getStartDate()?->getTimestamp() ?? PHP_INT_MIN;

            return $bStart <=> $aStart;
        });

        return array_merge($scheduled, $evergreen);
    }

    private function covers(InformationBarEntity $bar, \DateTimeInterface $now): bool
    {
        $start = $bar->getStartDate();
        $end = $bar->getEndDate();

        if ($start !== null && $now < $start) {
            return false;
        }

        if ($end !== null && $now > $end) {
            return false;
        }

        return true;
    }
}
