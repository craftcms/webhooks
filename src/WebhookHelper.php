<?php

namespace craft\webhooks;

use Composer\Autoload\ClassLoader;
use Craft;
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\Tag;
use craft\elements\User;
use craft\services\Assets;
use craft\services\Categories;
use craft\services\Elements;
use craft\services\Entries;
use craft\services\Globals;
use craft\services\Tags;
use craft\services\Users;
use yii\base\Component;
use yii\caching\FileDependency;

/**
 * Webhook Helper
 *
 * @since 1.2.0
 */
class WebhookHelper
{
    /**
     * Returns suggestions for the Sender Class input.
     *
     * @return array
     */
    public static function classSuggestions(): array
    {
        $data = Craft::$app->getCache()->getOrSet('webhooks.classSuggestions', function() {
            $classes = [];
            foreach (self::_findClasses() as $class) {
                $classes[] = [
                    'name' => $class,
                    'hint' => self::_shortDesc((new \ReflectionClass($class))->getDocComment()),
                ];
            }
            return $classes;
        }, null, new FileDependency([
            'fileName' => Craft::$app->getPath()->getVendorPath() . '/composer/autoload_real.php',
        ]));

        return [
            [
                'data' => $data,
            ],
        ];
    }

    /**
     * Returns suggestions for the Event Name input.
     *
     * @return array
     */
    public static function eventSuggestions(string $senderClass): array
    {
        if (!class_exists($senderClass) || !is_subclass_of($senderClass, Component::class)) {
            return [];
        }

        $events = [];

        foreach ((new \ReflectionClass($senderClass))->getConstants() as $name => $value) {
            if (strpos($name, 'EVENT_') === 0) {
                $events[] = [
                    'name' => $value,
                    'hint' => self::_shortDesc((new \ReflectionClassConstant($senderClass, $name))->getDocComment()),
                ];
            }
        }

        return $events;
    }

    /**
     * Returns known component classes.
     *
     * @return Component[]|string[]
     */
    private static function _findClasses(): array
    {
        // See if Composer has an optimized autoloader
        // h/t https://stackoverflow.com/a/46435124/1688568
        $autoloadClass = null;
        foreach (get_declared_classes() as $class) {
            if (strpos($class, 'ComposerAutoloaderInit') === 0) {
                $autoloadClass = $class;
                break;
            }
        }
        if ($autoloadClass !== null) {
            try {
                /** @var ClassLoader $classLoader */
                $classLoader = $autoloadClass::getLoader();
                foreach ($classLoader->getClassMap() as $class => $file) {
                    if (
                        !class_exists($class, false) &&
                        !interface_exists($class, false) &&
                        file_exists($file) &&
                        strpos($class, 'Codeception') !== 0 &&
                        substr($class, -4) !== 'Test' &&
                        substr($class, -8) !== 'TestCase'
                    ) {
                        require $file;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $classes = [
            Asset::class => true,
            Assets::class => true,
            Categories::class => true,
            Category::class => true,
            Element::class => true,
            Elements::class => true,
            Entries::class => true,
            Entry::class => true,
            Globals::class => true,
            GlobalSet::class => true,
            Tag::class => true,
            Tags::class => true,
            User::class => true,
            Users::class => true,
        ];

        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, Component::class)) {
                $classes[$class] = true;
            }
        }

        ksort($classes);
        return array_keys($classes);
    }

    /**
     * Returns the short description from a given PHPDoc comment.
     *
     * @param string $doc
     * @return string|null
     */
    private static function _shortDesc(string $doc)
    {
        foreach (preg_split("/\r\n|\n|\r/", $doc) as $line) {
            $line = preg_replace('/^[\/\*\s]*(?:@event\s+[^\s]+\s+)?/', '', $line);
            if (strpos($line, '@') === 0) {
                return null;
            }
            if ($line) {
                return $line;
            }
        }

        return null;
    }
}
