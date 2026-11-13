<?php

declare(strict_types=1);

namespace CraftCms\Webhooks;

use CraftCms\Cms\Plugin\PluginSettings;
use Override;

/**
 * Webhooks settings, set from Settings → Webhooks or `config/craft/webhooks.php`.
 *
 * @since 4.0.0
 */
class Settings extends PluginSettings
{
    /**
     * @var bool Whether all webhooks should be disabled.
     */
    public bool $disableAllWebhooks = false;

    /**
     * @var int The maximum depth that the plugin should go into objects/arrays when converting them to arrays for
     *          event payloads.
     */
    public int $maxDepth = 5;

    /**
     * @var int The maximum number of request attempts that should be made.
     */
    public int $maxAttempts = 1;

    /**
     * @var int|null The time delay in seconds that initial webhook request attempts should have.
     */
    public ?int $initialDelay = null;

    /**
     * @var int The time delay in seconds between request retries.
     */
    public int $retryDelay = 60;

    /**
     * @var int|null The time (in seconds) that request history should be saved in the database before being
     *               deletable via garbage collection.
     */
    public ?int $purgeDuration = 604800;

    /**
     * @var array<string, mixed> Custom Guzzle request options that should be applied to webhook requests.
     *                           Can only be set in `config/craft/webhooks.php`.
     */
    public array $guzzleConfig = [];

    /**
     * @var string[] Hostnames that are allowed to resolve to private IP addresses, e.g. `'my-api.internal'` or
     *               `'*.ddev.site'`. A leading `*.` wildcard matches any subdomain. Can only be set in
     *               `config/craft/webhooks.php`.
     */
    public array $allowedPrivateHosts = [];

    #[Override]
    public function getRules(): array
    {
        return [
            ...parent::getRules(),
            'disableAllWebhooks' => ['boolean'],
            'maxDepth' => ['integer', 'min:1'],
            'maxAttempts' => ['integer', 'min:1'],
            'initialDelay' => ['nullable', 'integer', 'min:0'],
            'retryDelay' => ['integer', 'min:0'],
            'purgeDuration' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Craft stores this data in the project config when the settings are saved. `guzzleConfig` and
     * `allowedPrivateHosts` can only be set from the config file, so they’re left out.
     */
    #[Override]
    public function validationData(): array
    {
        $data = parent::validationData();
        unset($data['guzzleConfig'], $data['allowedPrivateHosts']);

        return $data;
    }
}
