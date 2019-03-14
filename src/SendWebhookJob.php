<?php

namespace craft\webhooks;

use Craft;
use craft\queue\BaseJob;
use GuzzleHttp\RequestOptions;

/**
 * Send Webhook job
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class SendWebhookJob extends BaseJob
{
    /**
     * @var string The request type ('get' or 'post')
     */
    public $type = 'post';

    /**
     * @var string The URL to send a request to
     */
    public $url;

    /**
     * @var array|string|null The data to send in the request
     */
    public $data;

    /**
     * @inheritdoc
     */
    public function defaultDescription()
    {
        return Craft::t('webhooks', 'Sending webhook');
    }

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $options = [];
        if ($this->type === 'post' && $this->data !== null) {
            if (is_array($this->data)) {
                $options[RequestOptions::JSON] = $this->data;
            } else {
                $options[RequestOptions::BODY] = $this->data;
            }
        }

        $client = Craft::createGuzzleClient();
        $client->request($this->type, $this->url, $options);
    }
}
