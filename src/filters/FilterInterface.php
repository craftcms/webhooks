<?php

namespace craft\webhooks\filters;

use craft\base\ComponentInterface;
use yii\base\Event;

/**
 * Filter Interface
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1.0
 */
interface FilterInterface extends ComponentInterface
{
    /**
     * Returns whether the filter should be shown for the given class and event.
     *
     * @param string $class
     * @param string $event
     * @return bool
     */
    public static function show(string $class, string $event): bool;

    /**
     * Returns whether the event passes the filter.
     *
     * @param Event $event The event being filtered
     * @param bool $value The filter value
     * @return bool
     */
    public static function check(Event $event, bool $value): bool;
}
