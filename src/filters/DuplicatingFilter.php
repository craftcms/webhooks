<?php

namespace craft\webhooks\filters;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;

/**
 * Filters events based on whether the element is propagating
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1
 */
class DuplicatingFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return Craft::t('webhooks', 'Element is being duplicated');
    }

    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        /** @var Element $element */
        return (bool)$element->duplicateOf === $value;
    }
}
