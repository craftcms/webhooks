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
     * @var string The URL to send a request to
     */
    public $url;

    /**
     * @var array The data to send in the request
     */
    public $data;

    /**
     * @inheritdoc
     */
    public function execute($queue)
    {
        $client = Craft::createGuzzleClient();
        $client->post($this->url, [
            RequestOptions::JSON => $this->data,
        ]);
    }
}
