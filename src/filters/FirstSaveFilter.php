<?php

namespace craft\webhooks\filters;

use Craft;
use craft\base\ElementInterface;

/**
 * Filters events based on whether the element is propagating
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.4.0
 */
class FirstSaveFilter extends BaseElementFilter
{
    public static function displayName(): string
    {
        return Craft::t('webhooks', 'Element is being saved for the first time');
    }

    public static function excludes(): array
    {
        return [
            NewElementFilter::class,
            DraftFilter::class,
            ProvisionalDraftFilter::class,
            RevisionFilter::class,
            ResavingFilter::class,
        ];
    }

    protected static function checkElement(ElementInterface $element, bool $value): bool
    {
        return $element->firstSave === $value;
    }
}
