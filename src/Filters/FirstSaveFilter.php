<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Filters;

use CraftCms\Cms\Element\Contracts\ElementInterface;

use function CraftCms\Cms\t;

/**
 * Filters events based on whether the element is propagating.
 *
 * @since 2.4.0
 */
class FirstSaveFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return t('Element is being saved for the first time', category: 'webhooks');
    }

    public static function excludes(): array
    {
        return [
            NewElementFilter::class,
            DraftFilter::class,
            ProvisionalDraftFilter::class,
            RevisionFilter::class,
            ResavingFilter::class,
        ];
    }

    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        return $element->firstSave === $value;
    }
}
