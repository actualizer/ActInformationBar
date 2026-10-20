<?php declare(strict_types=1);

namespace Act\InformationBar\Service\Countdown;

use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Act\InformationBar\Service\InformationBarText;
use Act\InformationBar\Service\Text\BarTextFilter;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class CountdownViewFactory
{
    public function __construct(private readonly BarTextFilter $textFilter)
    {
    }

    public function create(InformationBarEntity $bar, InformationBarText $text, \DateTimeInterface $now): ?CountdownView
    {
        $target = $bar->getCountdownTarget();

        if ($target === null) {
            return null;
        }

        $expiredHtml = null;

        if ($text->countdownExpiredText !== null) {
            $lines = $this->textFilter->filterLine($text->countdownExpiredText)->lines;
            $expiredHtml = $lines[0] ?? null;
        }

        return new CountdownView(
            \DateTimeImmutable::createFromInterface($target)->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM),
            // A page rendered at or after the target must not show a running 00:00:00.
            $now >= $target,
            $expiredHtml,
        );
    }
}
