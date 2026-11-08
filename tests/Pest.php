<?php

declare(strict_types=1);

use CraftCms\Cms\Element\Events\ElementLifecycleSaved;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Entry\Models\Entry as EntryModel;
use CraftCms\Cms\Section\Models\Section;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Webhooks\Models\Webhook;
use CraftCms\Webhooks\Tests\TestCase;
use CraftCms\Webhooks\Webhooks;

uses(TestCase::class)->in('Feature');

/**
 * Creates an enabled webhook. Call {@see Webhooks::registerListeners()} afterward to listen for its event, as the
 * plugin does when it boots.
 *
 * @param  array<string, mixed>  $attributes
 */
function webhook(array $attributes = []): Webhook
{
    static $count = 0;

    return Webhook::query()->create([
        'name' => 'Webhook '.++$count,
        'class' => Entry::class,
        'event' => ElementLifecycleSaved::class,
        'method' => 'post',
        'url' => TestCase::RECEIVER_URL,
        'enabled' => true,
        ...$attributes,
    ]);
}

/**
 * Saves a new entry in the `news` section, as the control panel does, so its element events are triggered.
 */
function newsEntry(string $title = 'Hello world'): Entry
{
    // Entry factories write to the database directly, so an entry from one only provides the section and entry type
    $template = Entry::find()->section('news')->status(null)->one()
        ?? EntryModel::factory()->createElement([
            'sectionId' => Section::factory()->create(['handle' => 'news', 'name' => 'News'])->id,
        ]);

    $entry = new Entry([
        'sectionId' => $template->sectionId,
        'typeId' => $template->typeId,
        'title' => $title,
    ]);

    expect(Elements::saveElement($entry))->toBeTrue();

    return $entry;
}
