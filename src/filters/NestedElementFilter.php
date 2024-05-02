<?php

namespace craft\webhooks\filters;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\ElementHelper;

/**
 * Filters events based on whether the element is nested inside e.g. Matrix Field
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.1
 */
class NestedElementFilter extends BaseElementFilter
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('webhooks', 'Element is nested');
    }

    /**
     * @inheritdoc
     */
    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        // if element has a fieldId and has an owner - it's a nested element; e.g. entry inside a Matrix field
        return ($element->fieldId !== null && $element->getOwnerId() !== null) === $value;
    }
}
