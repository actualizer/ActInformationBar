<?php declare(strict_types=1);

namespace Act\InformationBar\Service;

use Act\InformationBar\Content\InformationBar\InformationBarCollection;
use Act\InformationBar\Content\InformationBar\InformationBarEntity;
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
 * Resolution order: for each entity candidate (sales-channel-specific record
 * first, global record second) try every language candidate (current language,
 * sales channel default language, system language) in order; the first
 * non-empty message wins.
 */
class InformationBarTextLoader
{
    /**
     * @param EntityRepository<InformationBarCollection> $informationBarRepository
     */
    public function __construct(private readonly EntityRepository $informationBarRepository)
    {
    }

    public function load(SalesChannelContext $context): InformationBarText
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

        return $this->resolve(
            $this->pickEntities($entities, $salesChannelId),
            [
                $context->getLanguageId(),
                $context->getSalesChannel()->getLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]
        );
    }

    /**
     * Orders the entity candidates: the record bound to the sales channel is
     * tried before the global one (salesChannelId === null). Either may be
     * absent from the search result.
     *
     * @return list<InformationBarEntity>
     */
    public function pickEntities(InformationBarCollection $entities, string $salesChannelId): array
    {
        $candidates = [];

        foreach ($entities as $entity) {
            if ($entity->getSalesChannelId() === $salesChannelId) {
                $candidates[] = $entity;
                break;
            }
        }

        foreach ($entities as $entity) {
            if ($entity->getSalesChannelId() === null) {
                $candidates[] = $entity;
                break;
            }
        }

        return $candidates;
    }

    /**
     * Tries every entity candidate against every language candidate, both in
     * priority order (entity candidates outer, language candidates inner);
     * the first non-empty message wins. Only if a whole entity yields no
     * usable translation for any language does the next entity get tried.
     *
     * @param list<InformationBarEntity> $entities
     * @param list<string|null> $languageIds
     */
    public function resolve(array $entities, array $languageIds): InformationBarText
    {
        foreach ($entities as $entity) {
            $translations = $entity->getTranslations();

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

                    return new InformationBarText(
                        $message,
                        $translation->getButtonText(),
                        $translation->getButtonTitle(),
                        $translation->getButtonUrl(),
                    );
                }
            }
        }

        return new InformationBarText();
    }
}
