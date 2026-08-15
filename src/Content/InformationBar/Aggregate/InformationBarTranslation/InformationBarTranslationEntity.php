<?php declare(strict_types=1);

namespace Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation;

use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class InformationBarTranslationEntity extends TranslationEntity
{
    // Reverse association auto-registered by EntityTranslationDefinition::getBaseFields()
    // as ManyToOneAssociationField('actInformationBar', ...); must be declared or the
    // DAL hydrator creates it as a deprecated dynamic property.
    protected ?InformationBarEntity $actInformationBar = null;

    protected ?string $message = null;

    protected ?string $buttonText = null;

    protected ?string $buttonTitle = null;

    protected ?string $buttonUrl = null;

    protected string $actInformationBarId;

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getButtonText(): ?string
    {
        return $this->buttonText;
    }

    public function setButtonText(?string $buttonText): void
    {
        $this->buttonText = $buttonText;
    }

    public function getButtonTitle(): ?string
    {
        return $this->buttonTitle;
    }

    public function setButtonTitle(?string $buttonTitle): void
    {
        $this->buttonTitle = $buttonTitle;
    }

    public function getButtonUrl(): ?string
    {
        return $this->buttonUrl;
    }

    public function setButtonUrl(?string $buttonUrl): void
    {
        $this->buttonUrl = $buttonUrl;
    }

    public function getActInformationBarId(): string
    {
        return $this->actInformationBarId;
    }

    public function setActInformationBarId(string $actInformationBarId): void
    {
        $this->actInformationBarId = $actInformationBarId;
    }

    public function getActInformationBar(): ?InformationBarEntity
    {
        return $this->actInformationBar;
    }

    public function setActInformationBar(InformationBarEntity $actInformationBar): void
    {
        $this->actInformationBar = $actInformationBar;
    }
}
