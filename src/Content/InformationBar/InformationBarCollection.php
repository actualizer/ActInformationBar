<?php declare(strict_types=1);

namespace Act\InformationBar\Content\InformationBar;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<InformationBarEntity>
 */
#[Package('discovery')]
class InformationBarCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return InformationBarEntity::class;
    }
}
