<?php

declare(strict_types=1);

use CraftCms\Cms\Element\Events\ElementLifecycleSaved;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Cms\GarbageCollection\GarbageCollection;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Webhooks\Enums\RequestStatus;
use CraftCms\Webhooks\Filters\NewElementFilter;
use CraftCms\Webhooks\Filters\RevisionFilter;
use CraftCms\Webhooks\Jobs\SendRequest;
use CraftCms\Webhooks\Models\WebhookRequest;
use CraftCms\Webhooks\Tests\TestCase;
use CraftCms\Webhooks\Webhooks;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('sends the default payload when an entry is saved', function () {
    Http::fake(['*' => Http::response('ok')]);
    webhook(['filters' => [RevisionFilter::class => false]]);
    webhook(['class' => User::class]);
    app(Webhooks::class)->registerListeners();

    $entry = newsEntry('Breaking news');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === TestCase::RECEIVER_URL
        && $request->hasHeader('Content-Type', 'application/json')
        && $request['name'] === 'ElementLifecycleSaved'
        && $request['eventClass'] === ElementLifecycleSaved::class
        && $request['senderClass'] === $entry::class
        && $request['sender']['id'] === $entry->id
        && $request['sender']['title'] === 'Breaking news'
        && $request['event']['isNew'] === true);

    expect(WebhookRequest::query()->sole())
        ->status->toBe(RequestStatus::Done)
        ->responseStatus->toBe(200)
        ->attempts->toBe(1);
});

it('only sends requests for events that pass the webhook’s filters', function () {
    Http::fake(['*' => Http::response('ok')]);
    webhook(['filters' => [NewElementFilter::class => true, RevisionFilter::class => false]]);
    app(Webhooks::class)->registerListeners();

    $entry = newsEntry();
    Elements::saveElement($entry);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['event']['isNew'] === true);
});

it('renders payload templates and headers, and parses environment variables when sending', function () {
    Http::fake(['*' => Http::response('ok')]);
    putenv('WEBHOOK_AUTH=Bearer s3cret');
    // The README tells people to allow `json_encode` in the Twig sandbox for payload templates like this one
    config()->push('craft.twig-sandbox.allowedFilters', 'json_encode');
    webhook([
        'filters' => [RevisionFilter::class => false],
        'url' => TestCase::RECEIVER_URL.'/{{ event.sender.id }}',
        'headers' => [['name' => 'Authorization', 'value' => '$WEBHOOK_AUTH']],
        // The README’s example
        'payloadTemplate' => <<<'TWIG'
            {% set entry = event.sender %}
            {{
              {
                time: 'now'|date('c'),
                user: currentUser.username ?? null,
                name: event.name,
                entry: {
                  id: entry.id,
                  title: entry.title,
                  slug: entry.slug,
                  isNew: event.isNew
                }
              }|json_encode|raw
            }}
            TWIG,
    ]);
    app(Webhooks::class)->registerListeners();

    $entry = newsEntry('Templated');

    Http::assertSent(fn (Request $request) => $request->url() === TestCase::RECEIVER_URL."/$entry->id"
        && $request->hasHeader('Authorization', 'Bearer s3cret')
        && $request['name'] === 'ElementLifecycleSaved'
        && $request['user'] === null
        && $request['entry'] === ['id' => $entry->id, 'title' => 'Templated', 'slug' => 'templated', 'isNew' => true]);

    // The secret isn’t stored with the request
    expect(WebhookRequest::query()->sole()->requestHeaders)->toMatchArray(['Authorization' => '$WEBHOOK_AUTH']);
})->after(fn () => putenv('WEBHOOK_AUTH'));

it('retries failed requests until maxAttempts is reached', function () {
    Http::fake(['*' => Http::response('nope', 500)]);
    webhook(['filters' => [RevisionFilter::class => false]]);
    app(Webhooks::class)->registerListeners();

    newsEntry();

    Http::assertSentCount(3);
    expect(WebhookRequest::query()->sole())
        ->status->toBe(RequestStatus::Done)
        ->responseStatus->toBe(500)
        ->attempts->toBe(3);
});

it('replaces pending requests that have the same debounce key', function () {
    Queue::fake();
    webhook([
        'filters' => [RevisionFilter::class => false],
        'debounceKeyFormat' => '{{ event.sender.id }}',
    ]);
    app(Webhooks::class)->registerListeners();

    $first = newsEntry('First');
    $first->title = 'Edited';
    Elements::saveElement($first);
    newsEntry('Second');

    $requests = WebhookRequest::query()->orderBy('id')->get();
    Queue::assertPushed(SendRequest::class, 2);
    expect($requests)->toHaveCount(2)
        ->and(Json::decode($requests[0]->requestBody)['sender']['title'])->toBe('Edited')
        ->and(Json::decode($requests[1]->requestBody)['sender']['title'])->toBe('Second');
});

it('blocks requests to private addresses unless their host is allowed', function (string $url, ?string $redirect, bool $delivered) {
    Http::fake(fn (Request $request) => $redirect && $request->url() === $url
        ? Http::response('', 302, ['Location' => $redirect])
        : Http::response('ok'));

    $request = app(Webhooks::class)->request('post', $url, body: '{}');

    // Blocked requests never reach the transport, so only the original URL can have been requested
    $requestedUrls = Http::recorded()->map(fn (array $pair) => $pair[0]->url())->unique()->values()->all();
    expect($requestedUrls)->toBe($delivered || $redirect ? [$url] : [])
        ->and($request->fresh()->responseStatus)->toBe($delivered ? 200 : null);
})->with([
    'IP address' => ['http://10.0.0.5/hook', null, false],
    'host that resolves to a metadata IP' => ['http://metadata.test/latest/meta-data', null, false],
    'private host that isn’t allowed' => ['http://other-internal.test/hook', null, false],
    'redirect to a metadata IP' => [TestCase::RECEIVER_URL, 'http://metadata.test/latest/meta-data', false],
    'allowed private host' => ['http://internal.test/hook', null, true],
]);

it('purges old sent requests during garbage collection', function () {
    $webhooks = app(Webhooks::class);
    Queue::fake();
    $old = $webhooks->request('get', TestCase::RECEIVER_URL);
    $old->update(['status' => RequestStatus::Done, 'dateCreated' => now()->subWeeks(2)]);
    $oldPending = $webhooks->request('get', TestCase::RECEIVER_URL);
    $oldPending->update(['dateCreated' => now()->subWeeks(2)]);
    $recent = $webhooks->request('get', TestCase::RECEIVER_URL);
    $recent->update(['status' => RequestStatus::Done]);

    event(new RunningGarbageCollection(app(GarbageCollection::class)));

    expect(WebhookRequest::query()->pluck('id')->all())->toEqualCanonicalizing([$oldPending->id, $recent->id]);
});
