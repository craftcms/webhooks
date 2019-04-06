<?php

namespace craft\webhooks;

use Craft;
use craft\db\Query;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\web\UrlManager;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use yii\base\Arrayable;
use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidArgumentException;

/**
 * Webhooks plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @property-read Settings $settings
 * @propery-read WebhookManager $webhookManager
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class Plugin extends \craft\base\Plugin
{
    // Constants
    // =========================================================================

    const STATUS_PENDING = 'pending';
    const STATUS_REQUESTED = 'requested';
    const STATUS_DONE = 'done';

    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public $schemaVersion = '2.0.1';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (!$this->isInstalled) {
            return;
        }

        // Set the webhookManager component
        $manager = new WebhookManager();
        $this->set('webhookManager', $manager);

        // Register webhook events
        try {
            $webhooks = $manager->getEnabledWebhooks();
        } catch (\Throwable $e) {
            Craft::error('Unable to fetch enabled webhooks: ' . $e->getMessage(), __METHOD__);
            Craft::$app->getErrorHandler()->logException($e);
            $webhooks = [];
        }

        foreach ($webhooks as $webhook) {
            Event::on($webhook->class, $webhook->event, function(Event $e) use ($webhook) {
                if ($webhook->method === 'post') {
                    // Build out the body data
                    if ($webhook->payloadTemplate) {
                        $json = Craft::$app->getView()->renderString($webhook->payloadTemplate, [
                            'event' => $e,
                        ]);
                        $data = Json::decodeIfJson($json);
                    } else {
                        $user = Craft::$app->getUser()->getIdentity();
                        $data = [
                            'time' => (new \DateTime())->format(\DateTime::ATOM),
                            'user' => $user ? $this->toArray($user, $webhook->getUserAttributes()) : null,
                            'name' => $e->name,
                            'senderClass' => get_class($e->sender),
                            'sender' => $this->toArray($e->sender, $webhook->getSenderAttributes()),
                            'eventClass' => get_class($e),
                            'event' => [],
                        ];

                        $eventAttributes = $webhook->getEventAttributes();
                        $ref = new \ReflectionClass($e);
                        foreach (ArrayHelper::toArray($e, [], false) as $name => $value) {
                            if (!$ref->hasProperty($name) || $ref->getProperty($name)->getDeclaringClass()->getName() !== Event::class) {
                                $data['event'][$name] = $this->toArray($value, $eventAttributes[$name] ?? []);
                            }
                        }
                    }
                }

                // Queue the send request up
                $headers = [];
                if (isset($data) && is_array($data)) {
                    $body = Json::encode($data);
                    $headers['Content-Type'] = 'application/json';
                } else {
                    $body = $data ?? null;
                }

                // Check if it exists and if we we should send if it doesnt.
                if (!$this->doesBodyHaveValue($body) && $this->getSettings()->dontSendEmptyRequestBody === true) {
                    Craft::warning('Ignored webhook '. $webhook->name .' because the body was empty.', __METHOD__);
                } else {
                    $this->request($webhook->method, $webhook->url, $headers, $body, $webhook->id);
                }
            });
        }

        // Register CP routes
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $e) {
            $e->rules['webhooks'] = 'webhooks/manage/index';
            $e->rules['webhooks/group/<groupId:\d+>'] = 'webhooks/manage/index';
            $e->rules['webhooks/new'] = 'webhooks/webhooks/edit';
            $e->rules['webhooks/<id:\d+>'] = 'webhooks/webhooks/edit';
            $e->rules['webhooks/activity'] = 'webhooks/activity/index';
        });
    }

    /**
     * Validation of the body param
     * @param $body
     * @return bool
     */
    public function doesBodyHaveValue($body) : bool
    {
        if (!$body) {
            return false;
        }

        // If its a string containing only spaces.
        if (is_string($body) && trim($body) === '') {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem()
    {
        $item = parent::getCpNavItem();
        $item['subnav'] = [
            'manage' => ['label' => Craft::t('webhooks', 'Manage Webhooks'), 'url' => 'webhooks'],
            'activity' => ['label' => Craft::t('webhooks', 'Activity'), 'url' => 'webhooks/activity'],
        ];
        return $item;
    }

    /**
     * Queues up a webhook request to be sent.
     *
     * @param string $method
     * @param string $url
     * @param array|null $headers
     * @param string|null $body
     * @param int|null $webhookId
     * @throws \yii\db\Exception
     */
    public function request(string $method, string $url, array $headers = null, string $body = null, int $webhookId = null)
    {
        $db = Craft::$app->getDb();
        $db->createCommand()
            ->insert('{{%webhookrequests}}', [
                'webhookId' => $webhookId,
                'status' => self::STATUS_PENDING,
                'method' => $method,
                'url' => $url,
                'requestHeaders' => $headers ? Json::encode($headers) : null,
                'requestBody' => $body,
                'dateCreated' => Db::prepareDateForDb(new \DateTime()),
                'uid' => StringHelper::UUID(),
            ], false)
            ->execute();

        Craft::$app->getQueue()->push(new SendRequestJob([
            'requestId' => $db->getLastInsertID('{{%webhookrequests}}'),
            'webhookId' => $webhookId,
        ]));
    }

    /**
     * Returns data for a request by its ID.
     *
     * @param int $requestId
     * @return array
     */
    public function getRequestData(int $requestId): array
    {
        $data = (new Query())
            ->from(['{{%webhookrequests}}'])
            ->where(['id' => $requestId])
            ->one();

        if (!$data) {
            throw new InvalidArgumentException('Invalid webhook request ID: ' . $this->requestId);
        }

        if ($data['requestHeaders']) {
            $data['requestHeaders'] = Json::decode($data['requestHeaders']);
        }
        if ($data['responseHeaders']) {
            $data['responseHeaders'] = Json::decode($data['responseHeaders']);
        }

        return $data;
    }

    /**
     * Sends a request by its ID.
     *
     * @param int $requestId
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @return bool Whether the request came back with a 2xx response
     */
    public function sendRequest(int $requestId): bool
    {
        // Acquire a lock on the request
        $lockName = 'webhook.' . $requestId;
        $mutex = Craft::$app->getMutex();
        if (!$mutex->acquire($lockName)) {
            throw new Exception('Could not acquire a lock for the webhook request ' . $requestId);
        }

        // Prepare the request options
        $options = [];
        $data = $this->getRequestData($requestId);
        if ($data['requestHeaders']) {
            $options[RequestOptions::HEADERS] = $data['requestHeaders'];
        }
        if ($data['requestBody']) {
            $options[RequestOptions::BODY] = $data['requestBody'];
        }

        // Update the request
        $db = Craft::$app->getDb();
        $db->createCommand()
            ->update('{{%webhookrequests}}', [
                'status' => self::STATUS_REQUESTED,
                'dateRequested' => Db::prepareDateForDb(new \DateTime()),
            ], ['id' => $requestId], [], false)
            ->execute();

        $startTime = microtime(true);
        try {
            $response = Craft::createGuzzleClient()->request($data['method'], $data['url'], $options);
            $success = true;
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $success = false;
        }

        // Update the request
        $time = round(1000 * (microtime(true) - $startTime));
        $attempt = ($data['attempts'] ?? 0) + 1;

        $db = Craft::$app->getDb();
        $db->createCommand()
            ->update('{{%webhookrequests}}', [
                'status' => self::STATUS_DONE,
                'attempts' => $attempt,
                'responseStatus' => $response ? $response->getStatusCode() : null,
                'responseHeaders' => $response ? Json::encode($response->getHeaders()) : null,
                'responseBody' => $response ? (string)$response->getBody() : null,
                'responseTime' => $time,
            ], ['id' => $requestId], [], false)
            ->execute();

        // Release the lock
        $mutex->release($lockName);

        return $success;
    }

    /**
     * Converts an object to an array, including the given extra attributes.
     *
     * @param mixed $object
     * @param string[] $extra
     * @param int $depth The current object depth
     * @return array
     */
    public function toArray($object, array $extra, int $depth = 1): array
    {
        if ($object instanceof Arrayable) {
            $arr = $object->toArray([], $extra, false);
        } else {
            $arr = ArrayHelper::toArray($object, [], false);
        }

        $indexedExtra = [];
        foreach ($extra as $field) {
            $fieldParts = explode('.', $field, 2);
            if (isset($fieldParts[1])) {
                $indexedExtra[$fieldParts[0]][] = $fieldParts[1];
            }
        }

        $settings = $this->getSettings();
        foreach ($arr as $k => $v) {
            if (is_array($v) || is_object($v)) {
                if ($depth === $settings->maxDepth) {
                    unset($arr[$k]);
                } else {
                    $arr[$k] = $this->toArray($v, $indexedExtra[$k] ?? [], $depth + 1);
                }
            }
        }

        if ($depth === 1) {
            $arr = Craft::$app->getSecurity()->redactIfSensitive('', $arr);
        }

        return $arr;
    }

    /**
     * Returns the webhook manager.
     *
     * @return WebhookManager
     */
    public function getWebhookManager(): WebhookManager
    {
        return $this->get('webhookManager');
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }
}
