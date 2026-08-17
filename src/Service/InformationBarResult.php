<?php declare(strict_types=1);

namespace Act\InformationBar\Service;

use Act\InformationBar\Content\InformationBar\InformationBarEntity;

/**
 * The bar that won resolution together with its texts. Both always come from the same
 * record — mixing texts of one bar with the styling of another produces a state nobody
 * can explain from the admin UI.
 */
final readonly class InformationBarResult
{
    public function __construct(
        public InformationBarEntity $bar,
        public InformationBarText $text,
    ) {
    }
}
