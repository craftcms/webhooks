<?php

namespace craft\webhooks\filters;

use craft\base\Component;
use craft\base\ElementInterface;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;
use yii\base\NotSupportedException;

/**
 * Base filter for elements
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1.0
 */
abstract class BaseElementFilter extends Component implements ExclusiveFilterInterface
{
    public static function show(string $class, string $event): bool
    {
        return (is_subclass_of($class, ElementInterface::class) || (
            $class === Elements::class &&
            in_array($event, [
                Elements::EVENT_BEFORE_DELETE_ELEMENT,
                Elements::EVENT_AFTER_DELETE_ELEMENT,
                Elements::EVENT_BEFORE_RESTORE_ELEMENT,
                Elements::EVENT_AFTER_RESTORE_ELEMENT,
                Elements::EVENT_BEFORE_SAVE_ELEMENT,
                Elements::EVENT_AFTER_SAVE_ELEMENT,
                Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI,
                Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI,
            ])
        ));
    }

    public static function excludes(): array
    {
        return [];
    }

    public static function check(Event $event, bool $value): bool
    {
        if ($event->sender instanceof ElementInterface) {
            return static::checkElement($event->sender, $value);
        }

        if ($event instanceof ElementEvent) {
            return static::checkElement($event->element, $value);
        }

        throw new NotSupportedException('Invalid element event: ' . get_class($event));
    }

    /**
     * Returns whether the element passes the filter.
     *
     * @param ElementInterface $element
     * @param bool $value
     * @return bool
     */
    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        return true;
    }
}
