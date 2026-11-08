<?php

namespace craft\webhooks\filters;

use Craft;
use craft\events\ElementEvent;
use craft\events\ModelEvent;
use yii\base\Event;

/**
 * Filters events based on whether the element is propagating
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1.0
 */
class NewElementFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return Craft::t('webhooks', 'Element is new');
    }

    public static function check(Event $event, bool $value): bool
    {
        if (
            $event instanceof ModelEvent ||
            $event instanceof ElementEvent
        ) {
            return (bool)$event->isNew === $value;
        }

        return true;
    }
}
