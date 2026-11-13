<?php

/**
 * Webhooks settings.
 *
 * Publish this file to `config/craft/webhooks.php` with `php artisan vendor:publish --tag=webhooks`.
 * Settings set here override the ones in Settings → Webhooks.
 */

return [
    // Whether all webhooks should be disabled
    // 'disableAllWebhooks' => false,

    // The maximum depth that the plugin should go into objects/arrays when converting them to arrays for payloads
    // 'maxDepth' => 5,

    // The maximum number of request attempts that should be made
    // 'maxAttempts' => 1,

    // The delay (in seconds) that initial webhook request attempts should have
    // 'initialDelay' => null,

    // The delay (in seconds) between webhook attempts
    // 'retryDelay' => 60,

    // The time (in seconds) that request history should be kept before garbage collection deletes it
    // 'purgeDuration' => 604800,

    // Custom Guzzle request options for webhook requests
    'guzzleConfig' => [],

    // Hostnames that are allowed to resolve to private IP addresses (e.g. `'my-api.internal'` or `'*.ddev.site'`)
    'allowedPrivateHosts' => [],
];
