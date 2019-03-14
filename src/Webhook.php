<?php

namespace craft\webhooks;

use Craft;
use craft\base\Model;
use craft\validators\UniqueValidator;
use craft\webhooks\records\Webhook as WebhookRecord;
use yii\validators\Validator;

/**
 * Webhook model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
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
     * @var string
     */
    public $type = 'post';

    /**
     * @var string
     */
    public $url;

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
    public $jsonPayloadTemplate;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'event', 'url'], 'trim'],
            [
                ['class'],
                'filter',
                'filter' => function(string $value) {
                    return trim($value, ' \\');
                }, 'skipOnArray' => true
            ],
            [['name', 'class', 'event', 'type', 'url'], 'required'],
            [['name'], UniqueValidator::class, 'targetClass' => WebhookRecord::class],
            [['groupId'], 'number'],
            [['enabled'], 'boolean'],
            [['type'], 'in', 'range' => ['get', 'post']],
            [['url'], 'url'],
            [
                ['class'],
                function(string $attribute, array $params = null, Validator $validator) {
                    if (!class_exists($this->class)) {
                        $validator->addError($this, $attribute, Craft::t('webhooks', 'Class {value} doesn’t exist.'));
                    }
                }
            ],
            [
                ['event'],
                function(string $attribute, array $params = null, Validator $validator) {
                    if (class_exists($this->class)) {
                        $foundEvent = false;
                        foreach ((new \ReflectionClass($this->class))->getConstants() as $name => $value) {
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
                }
            ],
            [['userAttributes', 'senderAttributes'], 'validateAttributeList'],
            [['eventAttributes'], 'validateAttributeList', 'params' => ['regex' => '/^[a-z]\w*\.[a-z]\w*(?:\.[a-z]\w*)*$/i']],
            [['jsonPayloadTemplate'], 'validateJson'],

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
     * @param string $attribute
     * @param array|null $params
     * @param \yii\validators\Validator $validator
     */
    public function validateJson(string $attribute, array $params = null, Validator $validator)
    {
        if (!$value = $this->$attribute) {
            return;
        }
        try {
            $twig = new \Twig_Environment(new \Twig_Loader_Array(['tpl' => $value]));
            $value = $twig->render('tpl', []);
        } catch (\Exception $exception) {
            $validator->addError($this, $attribute, 'TWIG - ' . $exception->getMessage(), ['value' => '']);
        }

        if (json_decode($value) === null) {
            $validator->addError($this, $attribute, 'JSON - ' . json_last_error_msg(), ['value' => '']);
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
            list($property, $attribute) = explode('.', $attribute, 2);
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
