<?php

namespace craft\webhooks;

use Craft;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ArrayHelper;
use craft\web\UrlManager;
use yii\base\Arrayable;
use yii\base\Event;

/**
 * Webhooks plugin
 *
 * @method static Plugin getInstance()
 * @propery-read WebhookManager $webhookManager
 *
 * @author       Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since        1.0
 */
class Plugin extends \craft\base\Plugin
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public $schemaVersion = '1.2.0';

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
                if ($webhook->type === 'post') {
                    // Build out the body data
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

                    if ($webhook->jsonPayloadTemplate) {
                        $parsed = $this->preparePayload($webhook->jsonPayloadTemplate, $data);
                        $data = json_decode($parsed, true);
                    }
                }

                // Queue the send request up
                Craft::$app->getQueue()->push(new SendWebhookJob([
                    'description' => Craft::t('webhooks', 'Sending webhook “{name}”', [
                        'name' => $webhook->name,
                    ]),
                    'type' => $webhook->type,
                    'url' => $webhook->url,
                    'data' => $data ?? null,
                ]));
            });
        }

        // Register CP routes
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $e) {
            $e->rules['webhooks'] = 'webhooks/index/index';
            $e->rules['webhooks/group/<groupId:\d+>'] = 'webhooks/index/index';
            $e->rules['webhooks/new'] = 'webhooks/webhooks/edit';
            $e->rules['webhooks/<id:\d+>'] = 'webhooks/webhooks/edit';
        });
    }

    /**
     * Converts an object to an array, including the given extra attributes.
     *
     * @param mixed $object
     * @param string[] $extra
     *
     * @return array
     */
    public function toArray($object, array $extra): array
    {
        if ($object instanceof Arrayable) {
            $arr = $object->toArray([], $extra);
        } else {
            $arr = ArrayHelper::toArray($object);
        }

        $arr = Craft::$app->getSecurity()->redactIfSensitive('', $arr);

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

    protected function preparePayload($template, $attributes = [])
    {
        // Available variables
        Craft::info(print_r(self::dot($attributes), true), $this->name);

        $twig = new \Twig_Environment(new \Twig_Loader_Array([
            'tpl' => $template,
        ]));
        return $twig->render('tpl', $attributes);
    }


    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param        $array
     * @param string $prepend
     *
     * @return array
     */
    public static function dot($array, $prepend = '')
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, static::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }
        return $results;
    }
}
