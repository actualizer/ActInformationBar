<?php declare(strict_types=1);

namespace Act\InformationBar\Service\Countdown;

use Shopware\Core\Framework\Log\Package;

/**
 * Render state of a bar's countdown block; $expiredHtml is already filtered and safe to output.
 */
#[Package('discovery')]
final readonly class CountdownView
{
    public function __construct(
        public string $target,
        public bool $expired,
        public ?string $expiredHtml,
    ) {
    }
}
