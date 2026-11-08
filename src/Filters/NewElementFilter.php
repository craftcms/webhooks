<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Filters;

use function CraftCms\Cms\t;

/**
 * Filters events based on whether the element is new.
 *
 * @since 2.1.0
 */
class NewElementFilter implements FilterInterface
{
    public static function displayName(): string
    {
        return t('Element is new', category: 'webhooks');
    }

    public static function show(string $eventClass): bool
    {
        return property_exists($eventClass, 'element') && property_exists($eventClass, 'isNew');
    }

    public static function check(object $event, bool $value): bool
    {
        if (! property_exists($event, 'isNew')) {
            return true;
        }

        return (bool) $event->isNew === $value;
    }
}
