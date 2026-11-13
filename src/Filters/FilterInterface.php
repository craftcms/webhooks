<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Filters;

use CraftCms\Webhooks\Events\FilterTypesResolving;

/**
 * A check that can determine whether a webhook should be executed.
 *
 * Register custom filter types by listening for {@see FilterTypesResolving}.
 *
 * @since 2.1.0
 */
interface FilterInterface
{
    /**
     * Returns the filter’s display name.
     */
    public static function displayName(): string;

    /**
     * Returns whether the filter should be shown for the given event class.
     *
     * @param  class-string  $eventClass
     */
    public static function show(string $eventClass): bool;

    /**
     * Returns whether the event passes the filter.
     *
     * @param  object  $event  The event being filtered
     * @param  bool  $value  The filter value
     */
    public static function check(object $event, bool $value): bool;
}
