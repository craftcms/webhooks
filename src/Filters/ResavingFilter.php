<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Filters;

use CraftCms\Cms\Element\Contracts\ElementInterface;

use function CraftCms\Cms\t;

/**
 * Filters events based on whether the element is being bulk-resaved.
 *
 * @since 2.1.0
 */
class ResavingFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return t('Element is being bulk-resaved', category: 'webhooks');
    }

    public static function excludes(): array
    {
        return [
            FirstSaveFilter::class,
        ];
    }

    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        return $element->resaving === $value;
    }
}
