<?php

namespace craft\webhooks;

use Craft;
use craft\db\Query;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\web\UrlManager;
use craft\webhooks\filters\DraftFilter;
use craft\webhooks\filters\DuplicatingFilter;
use craft\webhooks\filters\FilterInterface;
use craft\webhooks\filters\NewElementFilter;
use craft\webhooks\filters\PropagatingFilter;
use craft\webhooks\filters\ResavingFilter;
use craft\webhooks\filters\RevisionFilter;
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

    /**
     * @event RegisterComponentTypesEvent The event that is triggered when registering filter types.
     *
     * Filter types must implement [[FilterInterface]].
     * ---
     * ```php
     * use craft\events\RegisterComponentTypesEvent;
     * use craft\webhooks\Plugin as Webhooks;
     * use yii\base\Event;
     *
     * if (class_exists(Webhooks::class)) {
     *     Event::on(Webhooks::class,
     *         Webhooks::EVENT_REGISTER_FILTER_TYPES,
     *         function(RegisterComponentTypesEvent $event) {
     *             $event->types[] = MyFilterType::class;
     *         }
     *     );
     * }
     * ```
     * @since 2.1
     */
    const EVENT_REGISTER_FILTER_TYPES = 'registerFilterTypes';

    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public $schemaVersion = '2.2.0';

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
                // Make sure it passes the filters
                foreach ($webhook->filters as $filterClass => $filterValue) {
                    /** @var string|FilterInterface $filterClass */
                    if (class_exists($filterClass) && !$filterClass::check($e, $filterValue)) {
                        return;
                    }
                }

                $view = Craft::$app->getView();

                if (in_array($webhook->method, ['post', 'put'], true)) {
                    // Build out the body data
                    if ($webhook->payloadTemplate) {
                        $json = $view->renderString($webhook->payloadTemplate, [
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

                // Set the headers and body
                $headers = [];

                foreach ($webhook->headers as $header) {
                    $header['value'] = Craft::parseEnv($header['value']);
                    $header['value'] = $view->renderString($header['value'], [
                        'event' => $e,
                    ]);
                    // Get the trimmed lines
                    $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $header['value'])));
                    // Add to the header array one-by-one, ensuring that we don't overwrite existing values
                    foreach ($lines as $line) {
                        if (!isset($headers[$header['name']])) {
                            $headers[$header['name']] = $line;
                        } else {
                            if (!is_array($headers[$header['name']])) {
                                $headers[$header['name']] = [$headers[$header['name']]];
                            }
                            $headers[$header['name']][] = $line;
                        }
                    }
                }

                if (isset($data) && is_array($data)) {
                    $body = Json::encode($data);
                    $headers['Content-Type'] = 'application/json';
                } else {
                    $body = $data ?? null;
                }

                // Queue the send request up
                $url = Craft::parseEnv($webhook->url);
                $url = $view->renderString($url, [
                    'event' => $e,
                ]);
                $this->request($webhook->method, $url, $headers, $body, $webhook->id);
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

    /**
     * Returns all available filter classes.
     *
     * @return string[] The available field type classes
     */
    public function getAllFilters(): array
    {
        $filterTypes = [
            NewElementFilter::class,
            DraftFilter::class,
            RevisionFilter::class,
            DuplicatingFilter::class,
            PropagatingFilter::class,
            ResavingFilter::class,
        ];

        $event = new RegisterComponentTypesEvent([
            'types' => $filterTypes
        ]);
        $this->trigger(self::EVENT_REGISTER_FILTER_TYPES, $event);

        return $event->types;
    }
}
