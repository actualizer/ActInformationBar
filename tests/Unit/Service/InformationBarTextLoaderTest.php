<?php declare(strict_types=1);

namespace Act\InformationBar\Tests\Unit\Service;

use Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation\InformationBarTranslationCollection;
use Act\InformationBar\Content\InformationBar\Aggregate\InformationBarTranslation\InformationBarTranslationEntity;
use Act\InformationBar\Content\InformationBar\InformationBarCollection;
use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Act\InformationBar\Service\BarDefaultsProvider;
use Act\InformationBar\Service\BarScheduleResolver;
use Act\InformationBar\Service\InformationBarTextLoader;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

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

        $result = $this->loader()->resolveWinner([$bar], $this->languageCandidates());

        self::assertNotNull($result);
        self::assertSame('English', $result->text->message);
    }

    public function testFallsBackToSalesChannelDefaultLanguage(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, [self::DEFAULT_LANGUAGE_ID => 'Deutsch']);

        $result = $this->loader()->resolveWinner([$bar], $this->languageCandidates());

        self::assertNotNull($result);
        self::assertSame('Deutsch', $result->text->message);
    }

    public function testFallsBackToSystemLanguageWhenNeitherCurrentNorSalesChannelLanguagePresent(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, [Defaults::LANGUAGE_SYSTEM => 'System']);

        $result = $this->loader()->resolveWinner([$bar], $this->languageCandidates());

        self::assertNotNull($result);
        self::assertSame('System', $result->text->message);
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

        $result = $this->loader()->resolveWinner([$bar], $this->languageCandidates());

        self::assertNotNull($result);
        self::assertSame('Deutsch', $result->text->message);
    }

    public function testPrefersSalesChannelSpecificRecordOverGlobalOne(): void
    {
        $global = $this->bar(null, [self::CURRENT_LANGUAGE_ID => 'Global']);
        $specific = $this->bar(self::SALES_CHANNEL_ID, [self::CURRENT_LANGUAGE_ID => 'Spezifisch']);

        // Candidate order (specific before global) is BarScheduleResolver's responsibility
        // as of Task 3; here it is supplied directly to isolate resolveWinner's own logic.
        $result = $this->loader()->resolveWinner([$specific, $global], $this->languageCandidates());

        self::assertNotNull($result);
        self::assertSame('Spezifisch', $result->text->message);
    }

    public function testFallsThroughToNextCandidateWhenPreferredBarHasNoText(): void
    {
        $channelBar = $this->bar(self::SALES_CHANNEL_ID, []);
        $channelBar->setName('Kanal ohne Text');

        $globalBar = $this->bar(null, [Defaults::LANGUAGE_SYSTEM => 'Global']);
        $globalBar->setName('Global');

        $result = $this->loader()->resolveWinner(
            [$channelBar, $globalBar],
            $this->languageCandidates()
        );

        self::assertNotNull($result);
        self::assertSame('Global', $result->text->message);
        self::assertSame('Global', $result->bar->getName());
    }

    public function testReturnsNullWhenNothingMatches(): void
    {
        self::assertNull($this->loader()->resolveWinner([], $this->languageCandidates()));
    }

    /**
     * Order-sensitive: the sales-channel-specific record only has a
     * translation in the system language (the lowest-priority language
     * candidate); the global record has one in the current language (the
     * highest-priority language candidate). A correct implementation
     * exhausts every language candidate on the specific record first and
     * returns its match. This test fails if the loops are nested the wrong
     * way round (language-outer/entity-inner), because that mistake would
     * surface the global record's current-language match instead.
     */
    public function testTriesAllLanguagesOnFirstCandidateBeforeFallingBackToNext(): void
    {
        $specific = $this->bar(self::SALES_CHANNEL_ID, [Defaults::LANGUAGE_SYSTEM => 'Specific-System']);
        $global = $this->bar(null, [self::CURRENT_LANGUAGE_ID => 'Global-Current']);

        $result = $this->loader()->resolveWinner([$specific, $global], $this->languageCandidates());

        self::assertNotNull($result);
        self::assertSame('Specific-System', $result->text->message);
    }

    public function testWinnerCarriesTextAndStylingFromTheSameBar(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, [Defaults::LANGUAGE_SYSTEM => 'Text']);
        $bar->setName('Kanal');
        $bar->setBackgroundColor('#c00000');

        $result = $this->loader()->resolveWinner([$bar], $this->languageCandidates());

        self::assertNotNull($result);
        self::assertSame('Text', $result->text->message);
        self::assertSame('#c00000', $result->bar->getBackgroundColor());
    }

    public function testReturnsNullWhenNoCandidateHasAnyText(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, []);

        self::assertNull($this->loader()->resolveWinner([$bar], $this->languageCandidates()));
    }

    /**
     * Covers load() itself, not just resolveWinner(): this is where the
     * candidate order [current language, sales channel default language,
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
            new StaticEntityRepository([new InformationBarCollection([$bar])]),
            new BarScheduleResolver(),
            new MockClock(),
            new BarDefaultsProvider(new StaticSystemConfigService())
        );

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(self::SALES_CHANNEL_ID);
        $salesChannel->setLanguageId(self::DEFAULT_LANGUAGE_ID);

        $salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: new Context(new SystemSource(), [], Defaults::CURRENCY, [self::CURRENT_LANGUAGE_ID]),
            salesChannel: $salesChannel,
        );

        $result = $loader->load($salesChannelContext);

        self::assertNotNull($result);
        self::assertSame('Deutsch', $result->text->message);
    }

    /**
     * Note on scope: BarScheduleResolver::covers() compares \DateTimeInterface values,
     * and PHP's comparison operators on those always compare the absolute instant, not
     * the display timezone - so the zone conversion below can never change which bar
     * wins (verified empirically; see audit-fixes-report.md). What CAN regress is the
     * conversion itself being silently dropped (call getTimezone(), discard the result,
     * keep the clock's own UTC zone) - that is what this test guards, by inspecting the
     * $now instance that actually reaches the resolver.
     */
    public function testLoadPassesNowConvertedToTheConfiguredShopTimezoneToTheScheduleResolver(): void
    {
        $bar = $this->bar(self::SALES_CHANNEL_ID, [Defaults::LANGUAGE_SYSTEM => 'Text']);

        $spyResolver = new class extends BarScheduleResolver {
            public ?\DateTimeInterface $capturedNow = null;

            public function pickCandidates(array $bars, ?string $salesChannelId, \DateTimeInterface $now): array
            {
                $this->capturedNow = $now;

                return parent::pickCandidates($bars, $salesChannelId, $now);
            }
        };

        $loader = new InformationBarTextLoader(
            new StaticEntityRepository([new InformationBarCollection([$bar])]),
            $spyResolver,
            new MockClock(),
            new BarDefaultsProvider(new StaticSystemConfigService([
                BarDefaultsProvider::TIMEZONE_KEY => 'Asia/Tokyo',
            ]))
        );

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(self::SALES_CHANNEL_ID);
        $salesChannel->setLanguageId(self::DEFAULT_LANGUAGE_ID);

        $salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: new Context(new SystemSource(), [], Defaults::CURRENCY, [self::CURRENT_LANGUAGE_ID]),
            salesChannel: $salesChannel,
        );

        $loader->load($salesChannelContext);

        self::assertNotNull($spyResolver->capturedNow);
        self::assertSame('Asia/Tokyo', $spyResolver->capturedNow->getTimezone()->getName());
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
        return new InformationBarTextLoader(
            new StaticEntityRepository([new InformationBarCollection([])]),
            new BarScheduleResolver(),
            new MockClock(),
            new BarDefaultsProvider(new StaticSystemConfigService())
        );
    }
}
