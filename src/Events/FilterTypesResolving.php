<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Events;

use CraftCms\Webhooks\Filters\FilterInterface;

/**
 * Dispatched when the available webhook filter types are resolved.
 *
 * ```php
 * use CraftCms\Webhooks\Events\FilterTypesResolving;
 * use Illuminate\Support\Facades\Event;
 *
 * Event::listen(function (FilterTypesResolving $event) {
 *     $event->types[] = MyFilter::class;
 * });
 * ```
 *
 * @since 4.0.0
 */
class FilterTypesResolving
{
    /**
     * @param  array<class-string<FilterInterface>>  $types
     */
    public function __construct(
        public array $types,
    ) {}
}
