<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Filters;

use CraftCms\Cms\Element\Contracts\ElementInterface;

use function CraftCms\Cms\t;

/**
 * Filters events based on whether the element is propagating.
 *
 * @since 2.1.0
 */
class PropagatingFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return t('Element is being propagated', category: 'webhooks');
    }

    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        return $element->propagating === $value;
    }
}
