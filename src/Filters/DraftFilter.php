<?php

namespace craft\webhooks\filters;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\ElementHelper;

/**
 * Filters events based on whether the element is a draft
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.1.0
 */
class DraftFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return Craft::t('webhooks', 'Element is a draft');
    }

    public static function excludes(): array
    {
        return [
            RevisionFilter::class,
            FirstSaveFilter::class,
        ];
    }

    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        $root = ElementHelper::rootElement($element);
        return $root->getIsDraft() === $value;
    }
}
