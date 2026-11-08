<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Filters;

use CraftCms\Cms\Element\Contracts\ElementInterface;

use function CraftCms\Cms\t;

/**
 * Filters events based on whether the element is enabled.
 *
 * @since 2.3.1
 */
class ElementEnabledFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return t('Element is enabled', category: 'webhooks');
    }

    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        return $value === ($element->enabled && $element->getEnabledForSite());
    }
}
