<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit\Service\Countdown;

use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Act\InformationBar\Service\Countdown\CountdownView;
use Act\InformationBar\Service\Countdown\CountdownViewFactory;
use Act\InformationBar\Service\InformationBarText;
use Act\InformationBar\Service\Text\BarTextFilter;
use PHPUnit\Framework\TestCase;

class CountdownViewFactoryTest extends TestCase
{
    private const TARGET = '2026-11-26 23:00:00';

    public function testNoTargetMeansNoCountdown(): void
    {
        self::assertNull($this->create(new InformationBarEntity(), null, $this->utc(self::TARGET)));
    }

    public function testFutureTargetIsRunning(): void
    {
        $view = $this->create($this->bar(), null, $this->utc('2026-11-26 22:59:59'));

        self::assertNotNull($view);
        self::assertFalse($view->expired);
        self::assertSame('2026-11-26T23:00:00+00:00', $view->target);
    }

    public function testTargetReachedRendersExpiredState(): void
    {
        $atTarget = $this->create($this->bar(), null, $this->utc(self::TARGET));
        $after = $this->create($this->bar(), null, $this->utc('2026-11-26 23:00:01'));

        self::assertNotNull($atTarget);
        self::assertNotNull($after);
        self::assertTrue($atTarget->expired);
        self::assertTrue($after->expired);
    }

    public function testExpiredTextIsFiltered(): void
    {
        $view = $this->create($this->bar(), '<mark>Jetzt</mark> live<br>', $this->utc(self::TARGET));

        self::assertNotNull($view);
        self::assertSame('<mark>Jetzt</mark> live&lt;br&gt;', $view->expiredHtml);
    }

    public function testEmptyExpiredTextIsNull(): void
    {
        foreach (['', "\u{200B}"] as $text) {
            $view = $this->create($this->bar(), $text, $this->utc(self::TARGET));

            self::assertNotNull($view);
            self::assertNull($view->expiredHtml);
        }
    }

    public function testTargetIsEmittedInUtcRegardlessOfInputZone(): void
    {
        $bar = new InformationBarEntity();
        $bar->setCountdownTarget(new \DateTimeImmutable('2026-11-27 00:00:00', new \DateTimeZone('Europe/Berlin')));

        $view = $this->create($bar, null, $this->utc('2026-01-01 00:00:00'));

        self::assertNotNull($view);
        self::assertSame('2026-11-26T23:00:00+00:00', $view->target);
    }

    private function bar(): InformationBarEntity
    {
        $bar = new InformationBarEntity();
        $bar->setCountdownTarget($this->utc(self::TARGET));

        return $bar;
    }

    private function utc(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
    }

    private function create(InformationBarEntity $bar, ?string $expiredText, \DateTimeInterface $now): ?CountdownView
    {
        return (new CountdownViewFactory(new BarTextFilter()))
            ->create($bar, new InformationBarText('Text', null, null, null, $expiredText), $now);
    }
}
