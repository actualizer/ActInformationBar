<?php declare(strict_types=1);

namespace Act\InformationBar\Content\InformationBar;

use Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation\InformationBarTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('discovery')]
class InformationBarDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'act_information_bar';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return InformationBarEntity::class;
    }

    public function getCollectionClass(): string
    {
        return InformationBarCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            // NULL means "applies to every sales channel", mirroring the plugin configuration.
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware()),

            (new TranslatedField('message'))->addFlags(new ApiAware()),
            (new TranslatedField('buttonText'))->addFlags(new ApiAware()),
            (new TranslatedField('buttonTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('buttonUrl'))->addFlags(new ApiAware()),

            (new TranslationsAssociationField(InformationBarTranslationDefinition::class, 'act_information_bar_id'))->addFlags(new Required()),
        ]);
    }
}
