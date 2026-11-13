<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Listeners;

use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Webhooks\Enums\RequestStatus;
use CraftCms\Webhooks\Models\WebhookRequest;
use CraftCms\Webhooks\Settings;

/**
 * Deletes sent requests older than the `purgeDuration` setting when garbage collection runs.
 *
 * @since 4.0.0
 */
readonly class PurgeRequests
{
    public function __construct(
        private Settings $settings,
    ) {}

    public function handle(RunningGarbageCollection $event): void
    {
        if (! $this->settings->purgeDuration) {
            return;
        }

        WebhookRequest::query()
            ->where('status', RequestStatus::Done)
            ->where('dateCreated', '<', now()->subSeconds($this->settings->purgeDuration))
            ->delete();
    }
}
