<?php

declare(strict_types=1);

namespace CraftCms\Webhooks;

use Closure;
use CraftCms\Cms\Cp\Data\NavItem;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\UrlValidator\UrlValidator;
use CraftCms\Webhooks\Listeners\PurgeRequests;
use Override;

use function CraftCms\Cms\t;

/**
 * Webhooks plugin
 *
 * @method Settings getSettings()
 *
 * @since 1.0.0
 */
class Plugin extends BasePlugin
{
    /** The latest Webhooks 3.x version that can be upgraded from. */
    public string $minVersionRequired = '3.4.0';

    public string $schemaVersion = '4.0.0';

    public bool $hasCpSection = true;

    public bool $hasCpSettings = true;

    /** @var array<class-string, class-string|class-string[]> */
    protected array $events = [
        RunningGarbageCollection::class => PurgeRequests::class,
    ];

    public function boot(): void
    {
        $this->app->singleton(Settings::class, fn () => $this->getSettings());
        $this->app->singleton(Webhooks::class);
        $this->app->when(Webhooks::class)
            ->needs(Closure::class)
            ->give(fn () => fn () => new UrlValidator(options: [
                'allowedPrivateHosts' => $this->getSettings()->allowedPrivateHosts,
            ]));

        if ($this->isInstalled) {
            $this->app->make(Webhooks::class)->registerListeners();
        }
    }

    #[Override]
    public function getCpNavItem(): NavItem
    {
        return parent::getCpNavItem()->subnav([
            new NavItem()->label(t('Manage Webhooks', category: 'webhooks'))->href(route('webhooks.index')),
            new NavItem()->label(t('Activity', category: 'webhooks'))->href(route('webhooks.activity.index')),
        ]);
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        $number = fn (string $path, string $label, string $instructions, bool $required = true) => Field::make(
            t($label, category: 'webhooks'),
            Number::make($path)->size(5),
        )->instructions(t($instructions, category: 'webhooks'))->required($required);

        return Ui::make([
            Field::make(t('Disable All Webhooks', category: 'webhooks'), Lightswitch::make('disableAllWebhooks'))
                ->instructions(t('Whether all webhooks should be disabled.', category: 'webhooks')),
            $number('maxDepth', 'Max Payload Depth', 'The maximum depth that the plugin should go into objects/arrays when converting them to arrays for event payloads.'),
            $number('maxAttempts', 'Max Attempts', 'The maximum number of request attempts that should be made.'),
            $number('initialDelay', 'Initial Delay', 'The time delay in seconds that initial webhook requests should have.', false),
            $number('retryDelay', 'Retry Delay', 'The time delay in seconds between request attempts.'),
            $number('purgeDuration', 'Purge Duration', 'The time (in seconds) that request history should be saved in the database before being deletable via garbage collection.', false),
        ]);
    }

    #[Override]
    protected static function createSettings(): ?PluginSettings
    {
        return new Settings;
    }
}
