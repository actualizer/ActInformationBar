<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit\Service;

use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Act\InformationBar\Service\BarScheduleResolver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

class BarScheduleResolverTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testScheduledBarBeatsEvergreenBar(): void
    {
        $evergreen = $this->bar('Dauerleiste', self::SALES_CHANNEL_ID);
        $scheduled = $this->bar('Urlaub', self::SALES_CHANNEL_ID, '2026-09-16', '2026-09-30');

        $result = $this->resolve([$evergreen, $scheduled], '2026-09-20');

        self::assertSame('Urlaub', $result[0]->getName());
        self::assertSame('Dauerleiste', $result[1]->getName());
    }

    public function testEvergreenBarReturnsAfterScheduledWindowEnded(): void
    {
        $evergreen = $this->bar('Dauerleiste', self::SALES_CHANNEL_ID);
        $scheduled = $this->bar('Urlaub', self::SALES_CHANNEL_ID, '2026-09-16', '2026-09-30');

        $result = $this->resolve([$evergreen, $scheduled], '2026-10-01');

        self::assertSame('Dauerleiste', $result[0]->getName());
    }

    public function testLaterStartDateWinsAmongOverlappingScheduledBars(): void
    {
        $early = $this->bar('Aktion', self::SALES_CHANNEL_ID, '2026-11-01', '2026-12-31');
        $late = $this->bar('Heiligabend', self::SALES_CHANNEL_ID, '2026-12-24', '2026-12-24');

        $result = $this->resolve([$early, $late], '2026-12-24');

        self::assertSame('Heiligabend', $result[0]->getName());
        self::assertSame('Aktion', $result[1]->getName());
    }

    public function testSalesChannelBarIsOrderedBeforeGlobalBar(): void
    {
        $global = $this->bar('Global', null);
        $channel = $this->bar('Kanal', self::SALES_CHANNEL_ID);

        $result = $this->resolve([$global, $channel], '2026-09-20');

        self::assertSame('Kanal', $result[0]->getName());
        self::assertSame('Global', $result[1]->getName());
    }

    public function testOnlyGlobalBarIsStillReturnedWhenNoChannelBarExists(): void
    {
        $global = $this->bar('Global', null);

        $result = $this->resolve([$global], '2026-09-20');

        self::assertSame([$global], $result);
    }

    public function testInactiveBarIsNeverACandidate(): void
    {
        $inactive = $this->bar('Aus', self::SALES_CHANNEL_ID);
        $inactive->setActive(false);

        $result = $this->resolve([$inactive], '2026-09-20');

        self::assertSame([], $result);
    }

    public function testBarStartingExactlyNowIsIncluded(): void
    {
        $bar = $this->bar('Start', self::SALES_CHANNEL_ID, '2026-09-16 00:00:00', null);

        $result = $this->resolve([$bar], '2026-09-16 00:00:00');

        self::assertSame('Start', $result[0]->getName());
    }

    public function testBarOneSecondBeforeStartIsExcluded(): void
    {
        $bar = $this->bar('Start', self::SALES_CHANNEL_ID, '2026-09-16 00:00:00', null);

        $result = $this->resolve([$bar], '2026-09-15 23:59:59');

        self::assertSame([], $result);
    }

    public function testBarEndingExactlyNowIsStillIncluded(): void
    {
        $bar = $this->bar('Ende', self::SALES_CHANNEL_ID, null, '2026-09-30 23:59:59');

        $result = $this->resolve([$bar], '2026-09-30 23:59:59');

        self::assertSame('Ende', $result[0]->getName());
    }

    public function testBarOneSecondAfterEndIsExcluded(): void
    {
        $bar = $this->bar('Ende', self::SALES_CHANNEL_ID, null, '2026-09-30 23:59:59');

        $result = $this->resolve([$bar], '2026-10-01 00:00:00');

        self::assertSame([], $result);
    }

    public function testEmptyInputYieldsNoCandidates(): void
    {
        self::assertSame([], $this->resolve([], '2026-09-20'));
    }

    public function testWindowIsEvaluatedInTheGivenTimezoneNotTheServerTimezone(): void
    {
        // Start is 29.11. 00:00 Europe/Berlin, which is 28.11. 23:00 UTC.
        $bar = $this->bar('BlackFriday', self::SALES_CHANNEL_ID, '2026-11-29 00:00:00', null);

        $resolver = new BarScheduleResolver();

        $utcJustBefore = new \DateTimeImmutable('2026-11-28 22:59:59', new \DateTimeZone('UTC'));
        $berlinStart = new \DateTimeImmutable('2026-11-29 00:00:00', new \DateTimeZone('Europe/Berlin'));

        self::assertSame([], $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $utcJustBefore));
        self::assertCount(1, $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $berlinStart));
    }

    /**
     * The bar's window has an end but no start: it is running "since forever", which
     * covers() must still recognize as active before that end date is reached.
     */
    public function testBarWithOnlyEndDateIsRunningBeforeItEnds(): void
    {
        $bar = $this->bar('BisZum15', self::SALES_CHANNEL_ID, null, '2026-09-15 23:59:59');

        $result = $this->resolve([$bar], '2026-08-16 00:00:00');

        self::assertSame([$bar], $result);
    }

    public function testBarWithOnlyEndDateStopsRunningAfterItEnds(): void
    {
        $bar = $this->bar('BisZum15', self::SALES_CHANNEL_ID, null, '2026-09-15 23:59:59');

        $result = $this->resolve([$bar], '2026-09-16 00:00:00');

        self::assertSame([], $result);
    }

    /**
     * PHP_INT_MIN fallback for the missing start date: a bar without a start has
     * effectively "always" begun, so it must lose ordering to a concretely
     * scheduled bar covering the same instant. This is existing behaviour, not
     * changed by this test.
     */
    public function testBarWithOnlyEndDateLosesOrderingToAConcretelyScheduledBar(): void
    {
        $openEnded = $this->bar('BisZum30', self::SALES_CHANNEL_ID, null, '2026-09-30 23:59:59');
        $concrete = $this->bar('Aktion', self::SALES_CHANNEL_ID, '2026-09-01', '2026-09-30');

        $result = $this->resolve([$openEnded, $concrete], '2026-09-10');

        self::assertSame('Aktion', $result[0]->getName());
        self::assertSame('BisZum30', $result[1]->getName());
    }

    /**
     * Spring-forward day: 02:00-03:00 Europe/Berlin does not exist as local wall-clock time -
     * the clock jumps straight from 02:00 CET to 03:00 CEST. PHP does not reject the literal
     * "02:00"/"03:00" strings; it silently shifts them forward by the missing hour, so BOTH
     * boundaries normalize to the identical UTC instant (2026-03-29 01:00:00 UTC). The bar's
     * advertised one-hour window is therefore mechanically zero seconds wide - this is the
     * correct result of instant-based comparison given that (degenerate) input, not a defect.
     */
    public function testWindowSpanningTheSpringForwardGapCollapsesToASingleInstant(): void
    {
        $bar = $this->bar('Sommerzeit', self::SALES_CHANNEL_ID, '2026-03-29 02:00:00', '2026-03-29 03:00:00');

        $resolver = new BarScheduleResolver();

        $justBefore = new \DateTimeImmutable('2026-03-29 00:59:59', new \DateTimeZone('UTC'));
        $collapsedInstant = new \DateTimeImmutable('2026-03-29 01:00:00', new \DateTimeZone('UTC'));
        $justAfter = new \DateTimeImmutable('2026-03-29 01:00:01', new \DateTimeZone('UTC'));

        self::assertSame([], $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $justBefore));
        self::assertCount(1, $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $collapsedInstant));
        self::assertSame([], $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $justAfter));
    }

    /**
     * Fall-back day: 02:00-02:59 Europe/Berlin occurs twice (first as CEST, then again as CET
     * after the clock resets), so a window from 01:30 to 03:30 local time covers three real
     * hours, not two. Both passes through the doubled hour must still count as "covered" -
     * proven here via unambiguous UTC instants rather than the ambiguous local strings.
     */
    public function testWindowSpanningTheFallBackDoubledHourCoversBothOccurrences(): void
    {
        $bar = $this->bar('Winterzeit', self::SALES_CHANNEL_ID, '2026-10-25 01:30:00', '2026-10-25 03:30:00');

        $resolver = new BarScheduleResolver();

        $before = new \DateTimeImmutable('2026-10-24 23:29:59', new \DateTimeZone('UTC'));
        $firstPass = new \DateTimeImmutable('2026-10-25 00:15:00', new \DateTimeZone('UTC')); // first 02:15, CEST
        $secondPass = new \DateTimeImmutable('2026-10-25 01:15:00', new \DateTimeZone('UTC')); // second 02:15, CET
        $after = new \DateTimeImmutable('2026-10-25 02:30:01', new \DateTimeZone('UTC'));

        self::assertSame([], $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $before));
        self::assertCount(1, $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $firstPass));
        self::assertCount(1, $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $secondPass));
        self::assertSame([], $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $after));
    }

    /**
     * Documents actual behaviour, not a requirement: a startDate typed as "02:30" (inside the
     * nonexistent spring-forward hour) has no correct answer, since that local time never
     * occurred. PHP silently normalizes it forward by the DST gap to 03:30 CEST (01:30 UTC) -
     * a full hour later than a naive reading of "02:30" would suggest, with no error raised.
     */
    public function testStartDateInsideTheNonexistentSpringForwardHourIsSilentlyShiftedForward(): void
    {
        $bar = $this->bar('Luecke', self::SALES_CHANNEL_ID, '2026-03-29 02:30:00', null);

        $resolver = new BarScheduleResolver();

        $justBeforeNormalizedStart = new \DateTimeImmutable('2026-03-29 01:29:59', new \DateTimeZone('UTC'));
        $normalizedStart = new \DateTimeImmutable('2026-03-29 01:30:00', new \DateTimeZone('UTC'));

        self::assertSame([], $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $justBeforeNormalizedStart));
        self::assertCount(1, $resolver->pickCandidates([$bar], self::SALES_CHANNEL_ID, $normalizedStart));
    }

    /**
     * @param list<InformationBarEntity> $bars
     * @return list<InformationBarEntity>
     */
    private function resolve(array $bars, string $now): array
    {
        return (new BarScheduleResolver())->pickCandidates(
            $bars,
            self::SALES_CHANNEL_ID,
            new \DateTimeImmutable($now, new \DateTimeZone('Europe/Berlin'))
        );
    }

    private function bar(string $name, ?string $salesChannelId, ?string $start = null, ?string $end = null, string $timezone = 'Europe/Berlin'): InformationBarEntity
    {
        $zone = new \DateTimeZone($timezone);
        $bar = new InformationBarEntity();
        $bar->setUniqueIdentifier(Uuid::randomHex());
        $bar->setName($name);
        $bar->setActive(true);
        $bar->setSalesChannelId($salesChannelId);
        $bar->setStartDate($start === null ? null : new \DateTimeImmutable($start, $zone));
        $bar->setEndDate($end === null ? null : new \DateTimeImmutable($end, $zone));

        return $bar;
    }
}
