<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Jobs;

use CraftCms\Cms\Queue\Job;
use CraftCms\Webhooks\Models\Webhook;
use CraftCms\Webhooks\Models\WebhookRequest;
use CraftCms\Webhooks\Settings;
use CraftCms\Webhooks\Webhooks;
use Override;

use function CraftCms\Cms\t;

/**
 * Sends a webhook request, and queues another attempt if it fails and `maxAttempts` allows it.
 *
 * @since 1.0.0
 */
class SendRequest extends Job
{
    public function __construct(
        public readonly int $requestId,
        public readonly ?int $webhookId = null,
    ) {
        parent::__construct();
    }

    public function handle(Webhooks $webhooks, Settings $settings): void
    {
        if ($webhooks->send($this->requestId)) {
            return;
        }

        $attempts = (int) WebhookRequest::query()->whereKey($this->requestId)->value('attempts');

        if ($attempts < $settings->maxAttempts) {
            self::dispatch($this->requestId, $this->webhookId)->delay($settings->retryDelay);
        }
    }

    #[Override]
    protected function defaultDescription(): string
    {
        $name = $this->webhookId ? Webhook::query()->whereKey($this->webhookId)->value('name') : null;
        $description = $name
            ? t('Sending webhook “{name}”', ['name' => $name], 'webhooks')
            : t('Sending webhook', category: 'webhooks');

        $attempts = (int) WebhookRequest::query()->whereKey($this->requestId)->value('attempts');

        if ($attempts) {
            $description .= ' '.t('(attempt {num})', ['num' => $attempts + 1], 'webhooks');
        }

        return $description;
    }
}
