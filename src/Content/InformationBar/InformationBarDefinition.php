<?php declare(strict_types=1);

namespace Act\InformationBar\Content\InformationBar;

use Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation\InformationBarTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
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
            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new StringField('name', 'name'))->addFlags(new Required(), new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new DateTimeField('start_date', 'startDate'))->addFlags(new ApiAware()),
            (new DateTimeField('end_date', 'endDate'))->addFlags(new ApiAware()),

            (new BoolField('full_width', 'fullWidth'))->addFlags(new ApiAware()),
            (new IntField('display_duration', 'displayDuration'))->addFlags(new ApiAware()),
            (new BoolField('show_button', 'showButton'))->addFlags(new ApiAware()),
            (new StringField('button_target', 'buttonTarget'))->addFlags(new ApiAware()),
            (new StringField('text_color', 'textColor'))->addFlags(new ApiAware()),
            (new StringField('background_color', 'backgroundColor'))->addFlags(new ApiAware()),
            (new StringField('padding_top', 'paddingTop'))->addFlags(new ApiAware()),
            (new StringField('padding_bottom', 'paddingBottom'))->addFlags(new ApiAware()),
            (new StringField('font_size', 'fontSize'))->addFlags(new ApiAware()),
            (new StringField('button_text_color', 'buttonTextColor'))->addFlags(new ApiAware()),
            (new StringField('button_text_color_hover', 'buttonTextColorHover'))->addFlags(new ApiAware()),
            (new StringField('button_border_color', 'buttonBorderColor'))->addFlags(new ApiAware()),
            (new StringField('button_border_color_hover', 'buttonBorderColorHover'))->addFlags(new ApiAware()),
            (new StringField('button_border_width', 'buttonBorderWidth'))->addFlags(new ApiAware()),
            (new StringField('button_background_color', 'buttonBackgroundColor'))->addFlags(new ApiAware()),
            (new StringField('button_background_color_hover', 'buttonBackgroundColorHover'))->addFlags(new ApiAware()),

            (new TranslatedField('message'))->addFlags(new ApiAware()),
            (new TranslatedField('buttonText'))->addFlags(new ApiAware()),
            (new TranslatedField('buttonTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('buttonUrl'))->addFlags(new ApiAware()),

            (new TranslationsAssociationField(InformationBarTranslationDefinition::class, 'act_information_bar_id'))->addFlags(new Required()),
        ]);
    }
}
