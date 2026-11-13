<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Filters;

/**
 * A filter that excludes other filters from being available when it’s active.
 *
 * @since 2.4.0
 */
interface ExclusiveFilterInterface extends FilterInterface
{
    /**
     * Returns any filters that should be disabled if this filter is active.
     *
     * @return array<class-string<FilterInterface>>
     */
    public static function excludes(): array;
}
