<?php

namespace craft\webhooks\filters;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\events\ElementEvent;
use craft\events\ModelEvent;
use yii\base\Event;

/**
 * Filters events based on whether the element is enabled
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.3.1
 */
class ElementEnabledFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return Craft::t('webhooks', 'Element is enabled');
    }

    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        /** @var Element $element */
        return $value === ($element->enabled && $element->enabledForSite);
    }
}
