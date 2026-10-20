<?php declare(strict_types=1);

namespace Act\InformationBar\Service\Text;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
final readonly class FilteredText
{
    /**
     * @param list<string> $lines Escaped, well-formed HTML lines that are safe to output with |raw
     * @param list<string> $tagsShownAsText Tag-like fragments that stay visible as text
     */
    public function __construct(
        public array $lines,
        public array $tagsShownAsText,
        public int $invisibleRemoved,
    ) {
    }
}
