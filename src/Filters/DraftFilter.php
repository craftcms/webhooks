<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Filters;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\ElementHelper;

use function CraftCms\Cms\t;

/**
 * Filters events based on whether the element is a draft.
 *
 * @since 2.1.0
 */
class DraftFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return t('Element is a draft', category: 'webhooks');
    }

    public static function excludes(): array
    {
        return [
            RevisionFilter::class,
            FirstSaveFilter::class,
        ];
    }

    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        return ElementHelper::rootElement($element)->getIsDraft() === $value;
    }
}
