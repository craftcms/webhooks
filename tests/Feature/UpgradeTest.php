<?php

declare(strict_types=1);

use CraftCms\Cms\Element\Events\ElementLifecycleSaved;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\User\Events\UserActivated;
use CraftCms\Webhooks\Database\Migrations\Install;
use CraftCms\Webhooks\Filters\DraftFilter;
use CraftCms\Webhooks\Filters\NewElementFilter;
use CraftCms\Webhooks\Models\Webhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('converts Craft 5 webhooks to Craft 6 events', function () {
    $insert = fn (string $name, string $class, string $event, ?array $filters = null) => DB::table('webhooks')->insertGetId([
        'name' => $name,
        'class' => $class,
        'event' => $event,
        'filters' => $filters ? Json::encode($filters) : null,
        'method' => 'post',
        'url' => 'https://example.com',
        'enabled' => true,
        'dateCreated' => now(),
        'dateUpdated' => now(),
        'uid' => (string) Str::uuid(),
    ]);

    $entrySaved = $insert('Entry saved', 'craft\elements\Entry', 'afterSave', [
        'craft\webhooks\filters\DraftFilter' => false,
        'craft\webhooks\filters\NewElementFilter' => true,
    ]);
    $elementSaved = $insert('Element saved', '\craft\services\Elements', 'afterSaveElement');
    $userActivated = $insert('User activated', 'craft\services\Users', 'afterActivateUser');
    $pluginEvent = $insert('Plugin event', 'acme\Plugin', 'afterThing');

    (require dirname(__DIR__, 2).'/database/migrations/2026_11_08_000000_convert_craft_5_webhooks.php')->up();

    expect(Webhook::query()->find($entrySaved))
        ->class->toBe(Entry::class)
        ->event->toBe(ElementLifecycleSaved::class)
        ->filters->toBe([DraftFilter::class => false, NewElementFilter::class => true])
        ->enabled->toBeTrue()
        ->and(Webhook::query()->find($elementSaved))
        ->class->toBeNull()
        ->event->toBe(ElementSaved::class)
        ->and(Webhook::query()->find($userActivated)->event)->toBe(UserActivated::class)
        ->and(Webhook::query()->find($pluginEvent))
        ->class->toBe('acme\Plugin')
        ->event->toBe('afterThing')
        ->enabled->toBeFalse();
});

it('archives tables left over from a previous install', function () {
    webhook(['name' => 'Left over']);

    $install = new Install;
    $install->up();
    $install->up();

    expect(Schema::hasTable('webhooks_old1'))->toBeTrue()
        ->and(DB::table('webhooks_old')->pluck('name')->all())->toBe(['Left over'])
        ->and(Webhook::query()->count())->toBe(0)
        ->and(webhook(['name' => 'New'])->exists)->toBeTrue();
});
