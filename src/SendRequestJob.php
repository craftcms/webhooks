<?php

namespace craft\webhooks;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\queue\BaseJob;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

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
    public function defaultDescription()
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
    public function execute($queue)
    {
        $success = Plugin::getInstance()->sendRequest($this->requestId);

        if (!$success) {
            $attempts = $this->_data()['attempts'];
            $settings = Plugin::getInstance()->getSettings();
            if ($attempts < $settings->maxAttempts) {
                Craft::$app->getQueue()
                    ->delay($settings->retryDelay)
                    ->push(new self([
                        'requestId' => $this->requestId,
                    ]));
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
