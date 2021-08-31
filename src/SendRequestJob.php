<?php

namespace craft\webhooks;

use Craft;
use craft\helpers\Queue;
use craft\queue\BaseJob;
use yii\base\InvalidArgumentException;

/**
 * Send Request job
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0.0
 */
class SendRequestJob extends BaseJob
{
    /**
     * @var int The request ID to send
     */
    public $requestId;

    /**
     * @var int|null The webhook ID
     */
    public $webhookId;

    /**
     * @inheritdoc
     */
    public function defaultDescription(): ?string
    {
        if ($webhook = $this->_webhook()) {
            $description = Craft::t('webhooks', 'Sending webhook “{name}”', [
                'name' => $webhook->name,
            ]);
        } else {
            $description = Craft::t('webhooks', 'Sending webhook');
        }
        if ($attempts = $this->_data()['attempts']) {
            $description .= ' ' . Craft::t('webhooks', '(attempt {num})', [
                    'num' => $attempts + 1,
                ]);
        }
        return $description;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $success = Plugin::getInstance()->sendRequest($this->requestId);

        if (!$success) {
            $attempts = $this->_data()['attempts'];
            $settings = Plugin::getInstance()->getSettings();
            if ($attempts < $settings->maxAttempts) {
                Queue::push(new self([
                    'requestId' => $this->requestId,
                ]), null, $settings->retryDelay);
            }
        }
    }

    /**
     * Returns the request data.
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function _data(): array
    {
        return Plugin::getInstance()->getRequestData($this->requestId);
    }

    /**
     * Returns the webhook associated with this request (if any).
     *
     * @return Webhook|null
     */
    private function _webhook()
    {
        if (!$this->webhookId) {
            return null;
        }
        return Plugin::getInstance()->getWebhookManager()->getWebhookById($this->webhookId);
    }
}
