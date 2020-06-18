<?php

namespace craft\webhooks;

use craft\base\Model;
use craft\helpers\ArrayHelper;

class Settings extends Model
{
    /**
     * @var int The maximum depth that the plugin should go into objects/arrays when converting them to arrays for
     * event payloads.
     */
    public $maxDepth = 5;

    /**
     * @var int The maximum number of request attempts that should be made.
     */
    public $maxAttempts = 1;

    /**
     * @var int The time delay in seconds between request retrys.
     * @since 2.3.0
     */
    public $retryDelay = 60;

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true)
    {
        // attemptDelay → retryDelay
        if (($retryDelay = ArrayHelper::remove($values, 'attemptDelay')) !== null) {
            $values['retryDelay'] = $retryDelay;
        }

        parent::setAttributes($values, $safeOnly);
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['maxDepth', 'maxAttempts'], 'number', 'integerOnly' => true, 'min' => 1],
            [['retryDelay'], 'number', 'integerOnly' => true, 'min' => 0],
        ];
    }
}
