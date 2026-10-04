<?php declare(strict_types=1);

namespace Act\InformationBar\Service;

/**
 * HTTP cache tag of every storefront page, because each of them renders the bar or its absence.
 */
final class InformationBarCacheTag
{
    public const TAG = 'act-information-bar';
}
