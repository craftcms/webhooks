<?php

namespace craft\webhooks\records;

use craft\db\ActiveRecord;

/**
 * Webhook record
 *
 * @property int $id
 * @property int|null $groupId
 * @property string $name
 * @property string $class
 * @property string $event
 * @property string $url
 * @property string|null $userAttributes
 * @property string|null $senderAttributes
 * @property string|null $eventAttributes
 * @property string|null $payloadTemplate
 * @property bool $enabled
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class Webhook extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%webhooks}}';
    }
}
