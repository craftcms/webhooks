<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Filters;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Events\ElementSaved;

/**
 * Base filter for events that carry an element in their `element` property, such as
 * {@see ElementSaved}.
 *
 * @since 2.1.0
 */
abstract class BaseElementFilter implements ExclusiveFilterInterface
{
    public static function show(string $eventClass): bool
    {
        return property_exists($eventClass, 'element');
    }

    public static function excludes(): array
    {
        return [];
    }

    public static function check(object $event, bool $value): bool
    {
        $element = $event->element ?? null;

        if (! $element instanceof ElementInterface) {
            return true;
        }

        return static::checkElement($element, $value);
    }

    /**
     * Returns whether the element passes the filter.
     */
    abstract protected static function checkElement(ElementInterface $element, bool $value): bool;
}
