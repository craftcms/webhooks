<?php

declare(strict_types=1);

namespace CraftCms\Webhooks;

use Composer\Autoload\ClassLoader;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Elements\ContentBlock;
use CraftCms\Cms\Plugin\Plugins;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\User\Elements\User;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Throwable;

/**
 * Suggests the event classes and subject classes a webhook can be configured for.
 *
 * Event classes are found in the `Events` namespaces of Craft and installed plugins.
 *
 * @since 1.2.0
 */
class EventSuggestions
{
    public function __construct(
        private readonly Plugins $plugins,
    ) {}

    /**
     * Returns the event classes that webhooks can listen for.
     *
     * @return list<array{value: class-string, label: string, hint: string|null}>
     */
    public function events(): array
    {
        $namespaces = ['CraftCms\\Cms\\'];

        foreach ($this->plugins->getAllPlugins() as $plugin) {
            $namespaces[] = Str::beforeLast($plugin::class, '\\').'\\';
        }

        $namespaces = array_values(array_unique($namespaces));
        $key = 'webhooks.eventSuggestions.'.md5(implode(',', $namespaces).$this->installedTime());

        return Cache::remember($key, 86400, function () use ($namespaces) {
            $suggestions = [];

            foreach ($this->findEventClasses($namespaces) as $class) {
                $suggestions[] = [
                    'value' => $class,
                    'label' => $class,
                    'hint' => self::shortDescription((string) new ReflectionClass($class)->getDocComment()),
                ];
            }

            usort($suggestions, fn (array $a, array $b) => strcmp($a['value'], $b['value']));

            return $suggestions;
        });
    }

    /**
     * Returns suggested subject classes: the element types.
     *
     * @return list<class-string>
     */
    public function subjectClasses(): array
    {
        return [
            Element::class,
            Entry::class,
            Asset::class,
            User::class,
            Address::class,
            ContentBlock::class,
        ];
    }

    /**
     * @param  list<string>  $namespaces
     * @return list<class-string>
     */
    private function findEventClasses(array $namespaces): array
    {
        $classes = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $dirs) {
                if (! array_any($namespaces, fn (string $namespace) => str_starts_with($prefix, $namespace))) {
                    continue;
                }

                foreach ($dirs as $dir) {
                    foreach ($this->eventFiles($dir) as $relativePath) {
                        $class = $prefix.str_replace('/', '\\', substr($relativePath, 0, -4));

                        try {
                            if (class_exists($class) && ! new ReflectionClass($class)->isAbstract()) {
                                $classes[$class] = true;
                            }
                        } catch (Throwable) {
                        }
                    }
                }
            }
        }

        return array_keys($classes);
    }

    /**
     * Returns the PHP files in `Events` directories under the given directory, relative to it.
     *
     * @return list<string>
     */
    private function eventFiles(string $dir): array
    {
        $dir = realpath($dir);

        if ($dir === false || ! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && basename($file->getPath()) === 'Events') {
                $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($dir) + 1));
            }
        }

        return $files;
    }

    private function installedTime(): int
    {
        foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
            if (is_file($file = "$vendorDir/composer/installed.php")) {
                return (int) filemtime($file);
            }
        }

        return 0;
    }

    /**
     * Returns the first line of a doc comment’s description.
     */
    private static function shortDescription(string $doc): ?string
    {
        foreach (preg_split('/\R/', $doc) ?: [] as $line) {
            $line = trim((string) preg_replace('/^[\/\*\s]*(?:@event\s+\S+\s+)?/', '', $line));

            if (str_starts_with($line, '@')) {
                return null;
            }

            if ($line !== '') {
                return $line;
            }
        }

        return null;
    }
}
