<?php

namespace craft\webhooks;

use craft\base\Model;

/**
 * Webhook group model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0.0
 */
class Group extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string|null
     */
    public ?string $name = null;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name'], 'trim'],
            [['name'], 'required'],
        ];
    }
}
