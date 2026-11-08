<?php

namespace craft\webhooks\filters;

/**
 * Exclusive Filter Interface
 *
 * This can be used by filters which should exclude other filters from being available when this filter is active and enabled.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.4.0
 */
interface ExclusiveFilterInterface extends FilterInterface
{
    /**
     * Returns any filters that should be disabled if this filter is active and enabled.
     *
     * @return string[]
     */
    public static function excludes(): array;
}
