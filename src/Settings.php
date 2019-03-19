<?php

namespace craft\webhooks;

use craft\base\Model;

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
     * @var int The time delay in seconds between request attempts.
     */
    public $attemptDelay = 60;
}
