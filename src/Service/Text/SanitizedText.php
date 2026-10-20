<?php declare(strict_types=1);

namespace Act\InformationBar\Service\Text;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
final readonly class SanitizedText
{
    /**
     * @param list<string> $removedFragments Tags and comments removed from the input
     */
    public function __construct(
        public string $text,
        public array $removedFragments,
        public int $invisibleRemoved,
    ) {
    }
}
