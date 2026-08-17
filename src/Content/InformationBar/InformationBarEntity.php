<?php declare(strict_types=1);

namespace Act\InformationBar\Content\InformationBar;

use Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation\InformationBarTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('discovery')]
class InformationBarEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $salesChannelId = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected string $name = '';

    protected bool $active = true;

    protected ?\DateTimeInterface $startDate = null;

    protected ?\DateTimeInterface $endDate = null;

    protected bool $fullWidth = false;

    protected int $displayDuration = 3;

    protected bool $showButton = false;

    protected string $buttonTarget = '_self';

    protected ?string $textColor = null;

    protected ?string $backgroundColor = null;

    protected ?string $paddingTop = null;

    protected ?string $paddingBottom = null;

    protected ?string $fontSize = null;

    protected ?string $buttonTextColor = null;

    protected ?string $buttonTextColorHover = null;

    protected ?string $buttonBorderColor = null;

    protected ?string $buttonBorderColorHover = null;

    protected ?string $buttonBorderWidth = null;

    protected ?string $buttonBackgroundColor = null;

    protected ?string $buttonBackgroundColorHover = null;

    protected ?string $message = null;

    protected ?string $buttonText = null;

    protected ?string $buttonTitle = null;

    protected ?string $buttonUrl = null;

    protected ?InformationBarTranslationCollection $translations = null;

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getFullWidth(): bool
    {
        return $this->fullWidth;
    }

    public function setFullWidth(bool $fullWidth): void
    {
        $this->fullWidth = $fullWidth;
    }

    public function getDisplayDuration(): int
    {
        return $this->displayDuration;
    }

    public function setDisplayDuration(int $displayDuration): void
    {
        $this->displayDuration = $displayDuration;
    }

    public function getShowButton(): bool
    {
        return $this->showButton;
    }

    public function setShowButton(bool $showButton): void
    {
        $this->showButton = $showButton;
    }

    public function getButtonTarget(): string
    {
        return $this->buttonTarget;
    }

    public function setButtonTarget(string $buttonTarget): void
    {
        $this->buttonTarget = $buttonTarget;
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    public function setTextColor(?string $textColor): void
    {
        $this->textColor = $textColor;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(?string $backgroundColor): void
    {
        $this->backgroundColor = $backgroundColor;
    }

    public function getPaddingTop(): ?string
    {
        return $this->paddingTop;
    }

    public function setPaddingTop(?string $paddingTop): void
    {
        $this->paddingTop = $paddingTop;
    }

    public function getPaddingBottom(): ?string
    {
        return $this->paddingBottom;
    }

    public function setPaddingBottom(?string $paddingBottom): void
    {
        $this->paddingBottom = $paddingBottom;
    }

    public function getFontSize(): ?string
    {
        return $this->fontSize;
    }

    public function setFontSize(?string $fontSize): void
    {
        $this->fontSize = $fontSize;
    }

    public function getButtonTextColor(): ?string
    {
        return $this->buttonTextColor;
    }

    public function setButtonTextColor(?string $buttonTextColor): void
    {
        $this->buttonTextColor = $buttonTextColor;
    }

    public function getButtonTextColorHover(): ?string
    {
        return $this->buttonTextColorHover;
    }

    public function setButtonTextColorHover(?string $buttonTextColorHover): void
    {
        $this->buttonTextColorHover = $buttonTextColorHover;
    }

    public function getButtonBorderColor(): ?string
    {
        return $this->buttonBorderColor;
    }

    public function setButtonBorderColor(?string $buttonBorderColor): void
    {
        $this->buttonBorderColor = $buttonBorderColor;
    }

    public function getButtonBorderColorHover(): ?string
    {
        return $this->buttonBorderColorHover;
    }

    public function setButtonBorderColorHover(?string $buttonBorderColorHover): void
    {
        $this->buttonBorderColorHover = $buttonBorderColorHover;
    }

    public function getButtonBorderWidth(): ?string
    {
        return $this->buttonBorderWidth;
    }

    public function setButtonBorderWidth(?string $buttonBorderWidth): void
    {
        $this->buttonBorderWidth = $buttonBorderWidth;
    }

    public function getButtonBackgroundColor(): ?string
    {
        return $this->buttonBackgroundColor;
    }

    public function setButtonBackgroundColor(?string $buttonBackgroundColor): void
    {
        $this->buttonBackgroundColor = $buttonBackgroundColor;
    }

    public function getButtonBackgroundColorHover(): ?string
    {
        return $this->buttonBackgroundColorHover;
    }

    public function setButtonBackgroundColorHover(?string $buttonBackgroundColorHover): void
    {
        $this->buttonBackgroundColorHover = $buttonBackgroundColorHover;
    }

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

    public function getTranslations(): ?InformationBarTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(InformationBarTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
