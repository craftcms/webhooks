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
     * @var int|null The time delay in seconds that initial webhook request attempts should have.
     * @since 2.4.0
     */
    public $initialDelay;

    /**
     * @var int The time delay in seconds between request retries.
     * @since 2.3.0
     */
    public $retryDelay = 60;

    /**
     * @var int|null The time (in seconds) that request history should be saved in the database before being
     * deletable via garbage collection.
     * @since 3.4.0
     */
    public $purgeDuration = 604800;

    /**
     * @var array Custom config options that should be applied when creating Guzzle clients.
     * @since 2.3.0
     */
    public $guzzleConfig = [];

    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true)
    {
        // attemptDelay → retryDelay
        if (($retryDelay = ArrayHelper::remove($values, 'attemptDelay')) !== null) {
            $values['retryDelay'] = $retryDelay;
        }

        if (empty($values['initialDelay'])) {
            $values['initialDelay'] = null;
        }

        if (empty($values['purgeDuration'])) {
            $values['purgeDuration'] = null;
        } else if (is_numeric($values['purgeDuration'])) {
            $values['purgeDuration'] = (int)$values['purgeDuration'];
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
            [['initialDelay', 'retryDelay', 'purgeDuration'], 'number', 'integerOnly' => true, 'min' => 0],
        ];
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        $fields = parent::fields();
        // guzzleConfig can't be set from the UI so no point in storing it in the project config
        unset($fields['guzzleConfig']);
        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        return [
            'guzzleConfig',
        ];
    }
}
