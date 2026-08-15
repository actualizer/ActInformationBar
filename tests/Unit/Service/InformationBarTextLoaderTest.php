<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit\Service;

use Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation\InformationBarTranslationCollection;
use Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation\InformationBarTranslationEntity;
use Act\InformationBar\Content\InformationBar\InformationBarCollection;
use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Act\InformationBar\Service\InformationBarTextLoader;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

class InformationBarTextLoaderTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const CURRENT_LANGUAGE_ID = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const DEFAULT_LANGUAGE_ID = 'cccccccccccccccccccccccccccccccc';

    public function testPrefersTranslationOfCurrentLanguage(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, [
            self::CURRENT_LANGUAGE_ID => 'English',
            self::DEFAULT_LANGUAGE_ID => 'Deutsch',
        ]);

        $text = $this->loader()->resolve([$bar], $this->languageCandidates());

        self::assertSame('English', $text->message);
    }

    public function testFallsBackToSalesChannelDefaultLanguage(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, [self::DEFAULT_LANGUAGE_ID => 'Deutsch']);

        $text = $this->loader()->resolve([$bar], $this->languageCandidates());

        self::assertSame('Deutsch', $text->message);
    }

    public function testFallsBackToSystemLanguageWhenNeitherCurrentNorSalesChannelLanguagePresent(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, [Defaults::LANGUAGE_SYSTEM => 'System']);

        $text = $this->loader()->resolve([$bar], $this->languageCandidates());

        self::assertSame('System', $text->message);
    }

    /**
     * Sharpens language stage 2 against stage 3: the record carries
     * translations in BOTH the sales channel's default language and the
     * system language. Only the default-language one may win, because it
     * has higher priority. If someone swapped the sales-channel-default and
     * system-language positions in the candidate order, this would return
     * 'System' instead - see the mutation proof in the Fix-Runde 2 report
     * section.
     */
    public function testPrefersSalesChannelDefaultLanguageOverSystemLanguage(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, [
            self::DEFAULT_LANGUAGE_ID => 'Deutsch',
            Defaults::LANGUAGE_SYSTEM => 'System',
        ]);

        $text = $this->loader()->resolve([$bar], $this->languageCandidates());

        self::assertSame('Deutsch', $text->message);
    }

    public function testPrefersSalesChannelSpecificRecordOverGlobalOne(): void
    {
        $global = $this->bar(null, [self::CURRENT_LANGUAGE_ID => 'Global']);
        $specific = $this->bar(self::SALES_CHANNEL_ID, [self::CURRENT_LANGUAGE_ID => 'Spezifisch']);

        $picked = $this->loader()->pickEntities(new InformationBarCollection([$global, $specific]), self::SALES_CHANNEL_ID);
        $text = $this->loader()->resolve($picked, $this->languageCandidates());

        self::assertSame('Spezifisch', $text->message);
    }

    public function testPickEntitiesReturnsOnlyGlobalRecordWhenNoSalesChannelSpecificRecordExists(): void
    {
        $global = $this->bar(null, [self::CURRENT_LANGUAGE_ID => 'Global']);

        $picked = $this->loader()->pickEntities(new InformationBarCollection([$global]), self::SALES_CHANNEL_ID);

        self::assertSame([$global], $picked);
    }

    public function testFallsBackToGlobalEntityWhenSalesChannelSpecificHasNoUsableTranslation(): void
    {
        $specific = $this->bar(self::SALES_CHANNEL_ID, []);
        $global = $this->bar(null, [self::CURRENT_LANGUAGE_ID => 'Global']);

        $picked = $this->loader()->pickEntities(new InformationBarCollection([$specific, $global]), self::SALES_CHANNEL_ID);
        $text = $this->loader()->resolve($picked, $this->languageCandidates());

        self::assertSame('Global', $text->message);
    }

    public function testReturnsEmptyTextWhenNothingMatches(): void
    {
        $text = $this->loader()->resolve([], $this->languageCandidates());

        self::assertTrue($text->isEmpty());
    }

    /**
     * Order-sensitive: the sales-channel-specific record only has a
     * translation in the system language (the lowest-priority language
     * candidate); the global record has one in the current language (the
     * highest-priority language candidate). A correct implementation
     * exhausts every language candidate on the specific record first and
     * returns its match. This test fails if the entity candidate order is
     * swapped (specific vs. global) AND fails if the loops are nested the
     * wrong way round (language-outer/entity-inner), because either mistake
     * would surface the global record's current-language match instead.
     */
    public function testTriesAllLanguagesOnSalesChannelSpecificEntityBeforeFallingBackToGlobalEntity(): void
    {
        $specific = $this->bar(self::SALES_CHANNEL_ID, [Defaults::LANGUAGE_SYSTEM => 'Specific-System']);
        $global = $this->bar(null, [self::CURRENT_LANGUAGE_ID => 'Global-Current']);

        $picked = $this->loader()->pickEntities(new InformationBarCollection([$global, $specific]), self::SALES_CHANNEL_ID);
        $text = $this->loader()->resolve($picked, $this->languageCandidates());

        self::assertSame('Specific-System', $text->message);
    }

    /**
     * Covers load() itself, not just resolve()/pickEntities(): this is where
     * the candidate order [current language, sales channel default language,
     * system language] is actually assembled from a SalesChannelContext, and
     * exactly this assembly regressed once already (Fix-Runde 1). The record
     * has no translation for the current language, one for the sales channel
     * default language, and one for the system language - only the
     * sales-channel-default one may win. Built via the core's own
     * Shopware\Core\Test\Generator::generateSalesChannelContext() rather than
     * a hand-rolled mock, since SalesChannelContext has no public
     * constructor-friendly stub of its own.
     */
    public function testLoadAssemblesLanguageCandidatesInPriorityOrder(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, [
            self::DEFAULT_LANGUAGE_ID => 'Deutsch',
            Defaults::LANGUAGE_SYSTEM => 'System',
        ]);

        $loader = new InformationBarTextLoader(
            new StaticEntityRepository([new InformationBarCollection([$bar])])
        );

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(self::SALES_CHANNEL_ID);
        $salesChannel->setLanguageId(self::DEFAULT_LANGUAGE_ID);

        $salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: new Context(new SystemSource(), [], Defaults::CURRENCY, [self::CURRENT_LANGUAGE_ID]),
            salesChannel: $salesChannel,
        );

        $text = $loader->load($salesChannelContext);

        self::assertSame('Deutsch', $text->message);
    }

    /**
     * @return list<string>
     */
    private function languageCandidates(): array
    {
        return [self::CURRENT_LANGUAGE_ID, self::DEFAULT_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM];
    }

    /**
     * @param array<string, string> $messagesByLanguageId
     */
    private function bar(?string $salesChannelId, array $messagesByLanguageId): InformationBarEntity
    {
        $entity = new InformationBarEntity();
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setSalesChannelId($salesChannelId);

        $translations = new InformationBarTranslationCollection();

        foreach ($messagesByLanguageId as $languageId => $message) {
            $translation = new InformationBarTranslationEntity();
            $translation->setUniqueIdentifier(Uuid::randomHex());
            $translation->setLanguageId($languageId);
            $translation->setMessage($message);
            $translations->add($translation);
        }

        $entity->setTranslations($translations);

        return $entity;
    }

    private function loader(): InformationBarTextLoader
    {
        return new InformationBarTextLoader(new StaticEntityRepository([new InformationBarCollection([])]));
    }
}
