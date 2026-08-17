<?php declare(strict_types=1);

namespace Act\InformationBar\Service;

/**
 * The four translatable texts of the information bar, already resolved for one language.
 */
final readonly class InformationBarText
{
    public function __construct(
        public ?string $message = null,
        public ?string $buttonText = null,
        public ?string $buttonTitle = null,
        public ?string $buttonUrl = null,
    ) {
    }
}
