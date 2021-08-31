<?php

namespace craft\webhooks;

use Craft;
use craft\base\Model;
use craft\validators\UniqueValidator;
use craft\webhooks\records\Webhook as WebhookRecord;
use ReflectionClass;
use Twig\Error\Error as TwigError;
use yii\validators\Validator;

/**
 * Webhook model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0.0
 */
class Webhook extends Model
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int|null
     */
    public $groupId;

    /**
     * @var bool
     */
    public $enabled = true;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $class;

    /**
     * @var string
     */
    public $event;

    /**
     * @var array
     */
    public $filters = [];

    /**
     * @var string|null
     */
    public $debounceKeyFormat;

    /**
     * @var string
     */
    public $method = 'post';

    /**
     * @var string
     */
    public $url;

    /**
     * @var array
     */
    public $headers = [];

    /**
     * @var string|null
     */
    public $userAttributes;

    /**
     * @var string|null
     */
    public $senderAttributes;

    /**
     * @var string|null
     */
    public $eventAttributes;

    /**
     * @var string|null
     */
    public $payloadTemplate;

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'class' => Craft::t('webhooks', 'Sender Class'),
            'event' => Craft::t('webhooks', 'Event Name'),
            'name' => Craft::t('webhooks', 'Name'),
            'url' => Craft::t('webhooks', 'URL'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name', 'event', 'url'], 'trim'],
            [
                ['class'],
                'filter',
                'filter' => function(string $value) {
                    return trim($value, ' \\');
                }, 'skipOnArray' => true,
            ],
            [['name', 'class', 'event', 'method', 'url'], 'required'],
            [['name'], UniqueValidator::class, 'targetClass' => WebhookRecord::class],
            [['groupId'], 'number'],
            [['enabled'], 'boolean'],
            [['method'], 'in', 'range' => ['get', 'post', 'put']],
            [
                ['class'],
                function(string $attribute, array $params = null, Validator $validator) {
                    if (!class_exists($this->class)) {
                        $validator->addError($this, $attribute, Craft::t('webhooks', 'Class {value} doesn’t exist.'));
                    }
                },
            ],
            [
                ['event'],
                function(string $attribute, array $params = null, Validator $validator) {
                    if (class_exists($this->class)) {
                        $foundEvent = false;
                        foreach ((new ReflectionClass($this->class))->getConstants() as $name => $value) {
                            if (strpos($name, 'EVENT_') === 0) {
                                if ($value === $this->event) {
                                    $foundEvent = true;
                                    break;
                                }
                                if ($name === $this->event) {
                                    $this->event = $value;
                                    $foundEvent = true;
                                    break;
                                }
                            }
                        }
                        if (!$foundEvent) {
                            $validator->addError($this, $attribute, Craft::t('webhooks', 'Class {class} doesn’t appear to have a {value} event.', ['class' => $this->class]));
                        }
                    }
                },
            ],
            [['debounceKeyFormat'], 'string'],
            [
                ['filters'],
                function() {
                    foreach ($this->filters as $class => &$value) {
                        if ($value === 'yes') {
                            $value = true;
                        } else if ($value === 'no') {
                            $value = false;
                        }
                        if (!is_bool($value)) {
                            unset($this->filters[$class]);
                        }
                    }
                },
            ],
            [
                ['headers'],
                function() {
                    $this->headers = $this->headers ? array_values($this->headers) : [];
                },
            ],
            [['userAttributes', 'senderAttributes'], 'validateAttributeList'],
            [['eventAttributes'], 'validateAttributeList', 'params' => ['regex' => '/^[a-z]\w*\.[a-z]\w*(?:\.[a-z]\w*)*$/i']],
            [['payloadTemplate'], 'validatePayloadTemplate'],
        ];
    }

    /**
     * @param string $attribute
     * @param array|null $params
     * @param Validator $validator
     */
    public function validateAttributeList(string $attribute, array $params = null, Validator $validator)
    {
        $regex = $params['regex'] ?? '/^[a-z]\w*(?:\.[a-z]\w*)*$/i';

        $attributes = array_filter(array_map('trim', $this->_splitAttributes($this->$attribute)));
        $this->$attribute = implode("\n", $attributes);

        foreach ($attributes as $attr) {
            if (!preg_match($regex, $attr)) {
                $validator->addError($this, $attribute, Craft::t('webhooks', '{value} isn’t a valid attribute.'), ['value' => $attr]);
            }
        }
    }

    /**
     * Validates the JSON payload template.
     *
     * @param string $attribute
     * @param array|null $params
     * @param Validator $validator
     */
    public function validatePayloadTemplate(string $attribute, array $params = null, Validator $validator)
    {
        try {
            Craft::$app->getView()->getTwig()->createTemplate($this->payloadTemplate);
        } catch (TwigError $e) {
            $message = preg_replace('/ in "__string_template__\w+"/', '', $e->getMessage());
            $validator->addError($this, $attribute, $message);
        }
    }

    /**
     * @return string[]
     */
    public function getUserAttributes(): array
    {
        return $this->_splitAttributes($this->userAttributes);
    }

    /**
     * @return string[]
     */
    public function getSenderAttributes(): array
    {
        return $this->_splitAttributes($this->senderAttributes);
    }

    /**
     * @return array[]
     */
    public function getEventAttributes(): array
    {
        $split = $this->_splitAttributes($this->eventAttributes);
        $attributes = [];
        foreach ($split as $attribute) {
            [$property, $attribute] = explode('.', $attribute, 2);
            $attributes[$property][] = $$attribute;
        }
        return $attributes;
    }

    /**
     * @param string|null $attributes
     * @return string[]
     */
    private function _splitAttributes(string $attributes = null): array
    {
        return array_filter(preg_split('/[\r\n]+/', $attributes ?? ''));
    }
}
