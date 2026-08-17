<?php declare(strict_types=1);

namespace Act\InformationBar\Service;

use Act\InformationBar\Content\InformationBar\InformationBarCollection;
use Act\InformationBar\Content\InformationBar\InformationBarEntity;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves the information bar texts for the current sales channel and language.
 *
 * The translations are inspected explicitly instead of relying on the language
 * chain, because the fallback must land on the sales channel's default language
 * even when the current language has no parent relation to it, and it must
 * ultimately land on the system language, because a shop's pre-existing global
 * text (not tied to any sales channel) is stored under that language whenever
 * it has no translation of its own for the current or sales channel language.
 *
 * Candidate ordering itself is delegated to BarScheduleResolver; this class only
 * picks the first candidate with a usable translation, trying every language
 * candidate (current language, sales channel default language, system language)
 * in order before moving on to the next bar.
 */
class InformationBarTextLoader
{
    /**
     * @param EntityRepository<InformationBarCollection> $informationBarRepository
     */
    public function __construct(
        private readonly EntityRepository $informationBarRepository,
        private readonly BarScheduleResolver $scheduleResolver,
        private readonly ClockInterface $clock,
        private readonly BarDefaultsProvider $defaultsProvider,
    ) {
    }

    public function load(SalesChannelContext $context): ?InformationBarResult
    {
        $salesChannelId = $context->getSalesChannelId();

        $criteria = new Criteria();
        $criteria->addAssociation('translations');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('salesChannelId', $salesChannelId),
            new EqualsFilter('salesChannelId', null),
        ]));

        /** @var InformationBarCollection $entities */
        $entities = $this->informationBarRepository->search($criteria, $context->getContext())->getEntities();

        // Timezone is carried along for traceability, but has no effect on the result:
        // DateTimeInterface comparisons in BarScheduleResolver are instant-based, so this
        // setTimezone() call cannot change which bar wins - only instant-accurate storage does.
        $now = \DateTimeImmutable::createFromInterface($this->clock->now())
            ->setTimezone(new \DateTimeZone($this->defaultsProvider->getTimezone()));

        $candidates = $this->scheduleResolver->pickCandidates(
            array_values($entities->getElements()),
            $salesChannelId,
            $now
        );

        return $this->resolveWinner($candidates, [
            $context->getLanguageId(),
            $context->getSalesChannel()->getLanguageId(),
            Defaults::LANGUAGE_SYSTEM,
        ]);
    }

    /**
     * Tries every candidate bar against every language candidate, both in
     * priority order (bar candidates outer, language candidates inner); the
     * first non-empty message wins. Only if a whole bar yields no usable
     * translation for any language does the next bar get tried - collapsing
     * this to the first candidate only reintroduces the defect fixed in
     * 1.4.0, where a sales-channel bar without a translation hid the bar
     * entirely instead of falling through to the global one.
     *
     * @param list<InformationBarEntity> $candidates
     * @param list<string|null> $languageIds
     */
    public function resolveWinner(array $candidates, array $languageIds): ?InformationBarResult
    {
        foreach ($candidates as $bar) {
            $translations = $bar->getTranslations();

            if ($translations === null) {
                continue;
            }

            foreach ($languageIds as $languageId) {
                if ($languageId === null) {
                    continue;
                }

                foreach ($translations as $translation) {
                    if ($translation->getLanguageId() !== $languageId) {
                        continue;
                    }

                    $message = $translation->getMessage();

                    if ($message === null || $message === '') {
                        continue;
                    }

                    return new InformationBarResult($bar, new InformationBarText(
                        $message,
                        $translation->getButtonText(),
                        $translation->getButtonTitle(),
                        $translation->getButtonUrl(),
                    ));
                }
            }
        }

        return null;
    }
}
