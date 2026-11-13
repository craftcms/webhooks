<?php

declare(strict_types=1);

use CraftCms\Cms\Edition;
use CraftCms\Cms\Element\Events\ElementLifecycleSaved;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\Webhooks\Enums\RequestStatus;
use CraftCms\Webhooks\Filters\DraftFilter;
use CraftCms\Webhooks\Models\Webhook;
use CraftCms\Webhooks\Models\WebhookRequest;
use CraftCms\Webhooks\Tests\TestCase;
use CraftCms\Webhooks\Webhooks;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Edition::set(Edition::Pro);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function webhookForm(array $overrides = []): array
{
    return [
        'name' => 'Entry saved',
        'enabled' => true,
        'event' => ElementLifecycleSaved::class,
        'class' => Entry::class,
        'filters' => [DraftFilter::class => 'no'],
        'method' => 'post',
        'url' => TestCase::RECEIVER_URL,
        'headers' => [['name' => 'X-Token', 'value' => '$TOKEN']],
        'customPayload' => false,
        'senderAttributes' => "author\nsection.handle",
        ...$overrides,
    ];
}

it('requires permission to access the plugin', function (array $permissions, int $status) {
    actingAs(UserModel::factory()->withPermissions($permissions)->createElement());

    get(route('webhooks.index'))->assertStatus($status);
    get(route('webhooks.activity.index'))->assertStatus($status);
})->with([
    'control panel access only' => [['accessCp'], 403],
    'plugin access' => [['accessCp', 'accessPlugin-webhooks'], 200],
]);

it('shows its control panel screens', function () {
    Queue::fake();
    actingAs(User::find()->admin()->one());
    $webhook = webhook();
    $request = app(Webhooks::class)->request('post', TestCase::RECEIVER_URL, ['Content-Type' => 'application/json'], '{"a":1}', $webhook->id);

    foreach ([
        route('webhooks.index'),
        route('webhooks.create'),
        route('webhooks.edit', ['webhookId' => $webhook->id]),
        route('webhooks.groups.create'),
        route('webhooks.activity.index'),
        route('webhooks.activity.show', ['requestId' => $request->id]),
    ] as $url) {
        get($url)->assertOk();
    }
});

it('saves webhooks', function () {
    actingAs(User::find()->admin()->one());
    $url = TestCase::RECEIVER_URL.'?token='.str_repeat('a', 300);

    postJson(route('webhooks.store'), webhookForm(['url' => $url]))->assertOk();

    expect(Webhook::query()->sole())
        ->url->toBe($url)
        ->event->toBe(ElementLifecycleSaved::class)
        ->class->toBe(Entry::class)
        ->filters->toBe([DraftFilter::class => false])
        ->headers->toBe([['name' => 'X-Token', 'value' => '$TOKEN']])
        ->senderAttributes->toBe("author\nsection.handle")
        ->payloadTemplate->toBeNull();
});

it('rejects invalid webhooks', function (array $overrides, string $field) {
    actingAs(User::find()->admin()->one());

    postJson(route('webhooks.store'), webhookForm($overrides))->assertJsonValidationErrors($field);
    expect(Webhook::query()->count())->toBe(0);
})->with([
    'unknown event' => [['event' => 'App\Events\Nope'], 'event'],
    'private URL' => [['url' => 'http://other-internal.test/hook'], 'url'],
    'metadata URL' => [['url' => 'http://metadata.test/'], 'url'],
    'template syntax error' => [['customPayload' => true, 'payloadTemplate' => '{{ event.name '], 'payloadTemplate'],
    'filter the sandbox doesn’t allow' => [['customPayload' => true, 'payloadTemplate' => '{{ event|json_encode }}'], 'payloadTemplate'],
]);

it('only deletes requests that have been sent', function () {
    Queue::fake();
    actingAs(User::find()->admin()->one());
    $webhooks = app(Webhooks::class);
    $pending = $webhooks->request('post', TestCase::RECEIVER_URL);
    $sent = $webhooks->request('post', TestCase::RECEIVER_URL);
    $sent->update(['status' => RequestStatus::Done]);

    deleteJson(route('webhooks.activity.destroy'), ['ids' => [$pending->id, $sent->id]])->assertOk();

    expect(WebhookRequest::query()->pluck('id')->all())->toBe([$pending->id]);
});

it('redelivers requests', function () {
    Queue::fake();
    Http::fake(['*' => Http::response('ok')]);
    actingAs(User::find()->admin()->one());
    $request = app(Webhooks::class)->request('post', TestCase::RECEIVER_URL, body: '{}');

    postJson(route('webhooks.activity.redeliver', ['requestId' => $request->id]))->assertOk();

    Http::assertSentCount(1);
    expect($request->fresh())->responseStatus->toBe(200)->attempts->toBe(1);
});
