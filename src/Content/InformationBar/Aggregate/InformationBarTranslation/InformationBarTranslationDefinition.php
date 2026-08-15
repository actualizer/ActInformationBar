<?php declare(strict_types=1);

namespace Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation;

use Act\InformationBar\Content\InformationBar\InformationBarDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class InformationBarTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'act_information_bar_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return InformationBarTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return InformationBarTranslationCollection::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return InformationBarDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new LongTextField('message', 'message'))->addFlags(new ApiAware()),
            (new StringField('button_text', 'buttonText'))->addFlags(new ApiAware()),
            (new StringField('button_title', 'buttonTitle'))->addFlags(new ApiAware()),
            (new StringField('button_url', 'buttonUrl'))->addFlags(new ApiAware()),
        ]);
    }
}
