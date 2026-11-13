<?php

declare(strict_types=1);

namespace CraftCms\Webhooks;

use Closure;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\Security;
use CraftCms\Cms\Support\Json;
use CraftCms\UrlValidator\GuzzleMiddleware;
use CraftCms\UrlValidator\UrlValidationException;
use CraftCms\UrlValidator\UrlValidator;
use CraftCms\Webhooks\Enums\RequestStatus;
use CraftCms\Webhooks\Events\FilterTypesResolving;
use CraftCms\Webhooks\Filters\DraftFilter;
use CraftCms\Webhooks\Filters\DuplicatingFilter;
use CraftCms\Webhooks\Filters\ElementEnabledFilter;
use CraftCms\Webhooks\Filters\FilterInterface;
use CraftCms\Webhooks\Filters\FirstSaveFilter;
use CraftCms\Webhooks\Filters\NewElementFilter;
use CraftCms\Webhooks\Filters\PropagatingFilter;
use CraftCms\Webhooks\Filters\ProvisionalDraftFilter;
use CraftCms\Webhooks\Filters\ResavingFilter;
use CraftCms\Webhooks\Filters\RevisionFilter;
use CraftCms\Webhooks\Jobs\SendRequest;
use CraftCms\Webhooks\Models\Webhook;
use CraftCms\Webhooks\Models\WebhookRequest;
use DateTimeInterface;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\renderSandboxedString;

/**
 * Listens for the events that enabled webhooks are configured for, and queues and sends their requests.
 *
 * @since 4.0.0
 */
class Webhooks
{
    private ?UrlValidator $urlValidator = null;

    /**
     * @param  Closure(): UrlValidator  $makeUrlValidator  Creates the URL validator. It’s called the first time a URL
     *                                                     is validated, so an invalid `allowedPrivateHosts` setting
     *                                                     can’t stop Craft from booting.
     */
    public function __construct(
        private readonly Settings $settings,
        private readonly Closure $makeUrlValidator,
    ) {}

    /**
     * Returns the URL validator used for webhook URLs.
     *
     * Only the hosts listed in the `allowedPrivateHosts` setting can resolve to private IP addresses.
     *
     * @throws InvalidArgumentException if an `allowedPrivateHosts` entry isn’t a valid pattern
     */
    public function urlValidator(): UrlValidator
    {
        return $this->urlValidator ??= ($this->makeUrlValidator)();
    }

    /**
     * Registers a listener for each event that an enabled webhook is configured for.
     */
    public function registerListeners(): void
    {
        if ($this->settings->disableAllWebhooks) {
            return;
        }

        try {
            $webhooks = Webhook::query()->enabled()->get();
        } catch (Throwable $e) {
            Log::error('Unable to fetch enabled webhooks: '.$e->getMessage());
            report($e);

            return;
        }

        foreach ($webhooks->groupBy('event') as $eventClass => $group) {
            if (! class_exists($eventClass)) {
                continue;
            }

            Event::listen($eventClass, function (object $event) use ($group): void {
                foreach ($group as $webhook) {
                    $this->trigger($webhook, $event);
                }
            });
        }
    }

    /**
     * Queues the webhook’s request for an event, if the event matches its subject class and filters.
     *
     * Errors are logged rather than thrown, so a broken webhook can’t stop the action that triggered it.
     */
    public function trigger(Webhook $webhook, object $event): ?WebhookRequest
    {
        try {
            return $this->triggerInternal($webhook, $event);
        } catch (Throwable $e) {
            Log::error("Unable to trigger webhook “{$webhook->name}”: {$e->getMessage()}");
            report($e);

            return null;
        }
    }

    private function triggerInternal(Webhook $webhook, object $event): ?WebhookRequest
    {
        if (! $webhook->url) {
            return null;
        }

        $subject = $this->subject($webhook, $event);

        if ($webhook->class && $subject === null) {
            return null;
        }

        foreach ($webhook->filters ?? [] as $filterClass => $value) {
            if (is_subclass_of($filterClass, FilterInterface::class) && ! $filterClass::check($event, (bool) $value)) {
                return null;
            }
        }

        $variables = ['event' => $this->twigEvent($event, $subject)];
        $headers = [];
        $body = null;

        if ($webhook->hasBody()) {
            $data = $webhook->payloadTemplate
                ? Json::decodeIfJson(renderSandboxedString($webhook->payloadTemplate, $variables))
                : $this->defaultPayload($webhook, $event, $subject);

            if (is_array($data)) {
                $body = Json::encode($data);
                $json = true;
            } else {
                $body = (string) $data;
            }
        }

        foreach ($webhook->headers ?? [] as $header) {
            $value = renderSandboxedString($header['value'], $variables);
            $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $value) ?: []));

            // Add the lines one by one, without overwriting earlier values for the same header
            foreach ($lines as $line) {
                $name = $header['name'];

                if (! isset($headers[$name])) {
                    $headers[$name] = $line;
                } else {
                    $headers[$name] = [...(array) $headers[$name], $line];
                }
            }
        }

        if ($json ?? false) {
            $headers['Content-Type'] = 'application/json';
        }

        $debounceKey = null;

        if ($webhook->debounceKeyFormat && ($format = Env::parse($webhook->debounceKeyFormat))) {
            $debounceKey = "$webhook->id:".renderSandboxedString($format, $variables);
        }

        return $this->request(
            $webhook->method,
            renderSandboxedString($webhook->url, $variables),
            $headers,
            $body,
            $webhook->id,
            $debounceKey,
        );
    }

    /**
     * Queues up a webhook request to be sent.
     *
     * If a pending request has the same debounce key, it’s replaced instead.
     *
     * @param  array<string, string|string[]>  $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?int $webhookId = null,
        ?string $debounceKey = null,
    ): WebhookRequest {
        $attributes = [
            'method' => $method,
            'url' => $url,
            'requestHeaders' => $headers ?: null,
            'requestBody' => $body,
            'dateCreated' => now(),
        ];

        if ($debounceKey !== null) {
            $pending = WebhookRequest::query()
                ->where('debounceKey', $debounceKey)
                ->where('status', RequestStatus::Pending)
                ->first();

            $lock = $pending ? Cache::lock($this->lockKey($pending->id), 10) : null;

            if ($pending && $lock->get()) {
                try {
                    $pending->update($attributes);
                } finally {
                    $lock->release();
                }

                return $pending;
            }
        }

        $request = WebhookRequest::query()->create([
            ...$attributes,
            'webhookId' => $webhookId,
            'debounceKey' => $debounceKey,
            'status' => RequestStatus::Pending,
        ]);

        $job = SendRequest::dispatch($request->id, $webhookId)->afterCommit();

        if ($this->settings->initialDelay) {
            $job->delay($this->settings->initialDelay);
        }

        return $request;
    }

    /**
     * Sends a request, and records its response.
     *
     * Environment variables in the URL and header values are parsed now, so their values aren’t stored.
     *
     * @return bool Whether the request came back with a 2xx response. False if the request no longer exists.
     */
    public function send(int $requestId): bool
    {
        $request = WebhookRequest::query()->find($requestId);

        // It was deleted from the Activity page after its job was queued
        if (! $request) {
            return false;
        }

        // Throws if another worker is already sending it
        $lock = Cache::lock($this->lockKey($requestId), 300);
        $lock->block(1);

        try {
            $request->update([
                'status' => RequestStatus::Requested,
                'dateRequested' => now(),
            ]);

            $headers = array_map(
                fn (string|array $value) => is_array($value) ? array_map(Env::parse(...), $value) : Env::parse($value),
                $request->requestHeaders ?? [],
            );

            $options = $request->requestBody !== null && $request->requestBody !== ''
                ? ['body' => $request->requestBody]
                : [];

            $start = hrtime(true);
            $response = null;

            try {
                $response = Http::create($this->settings->guzzleConfig)
                    ->withHeaders($headers)
                    // Validates the URL and every redirect target, and pins them to the validated IPs
                    ->withMiddleware(GuzzleMiddleware::validateRequests($this->urlValidator()))
                    ->send(strtoupper($request->method), (string) Env::parse($request->url), $options);
            } catch (RequestException $e) {
                $response = $e->response;
                Log::warning("Unable to send webhook: {$e->getMessage()}");
            } catch (ConnectionException|TransferException|UrlValidationException|InvalidArgumentException $e) {
                Log::warning("Unable to send webhook: {$e->getMessage()}");
            }

            $request->update([
                'status' => RequestStatus::Done,
                'attempts' => ($request->attempts ?? 0) + 1,
                'responseStatus' => $response?->status(),
                'responseHeaders' => $response?->headers(),
                'responseBody' => $response?->body(),
                'responseTime' => (int) round((hrtime(true) - $start) / 1e6),
            ]);

            return $response instanceof Response && $response->successful();
        } finally {
            $lock->release();
        }
    }

    /**
     * Returns all available filter classes.
     *
     * @return array<class-string<FilterInterface>>
     */
    public function filterTypes(): array
    {
        event($event = new FilterTypesResolving([
            NewElementFilter::class,
            ElementEnabledFilter::class,
            DraftFilter::class,
            ProvisionalDraftFilter::class,
            RevisionFilter::class,
            FirstSaveFilter::class,
            DuplicatingFilter::class,
            PropagatingFilter::class,
            ResavingFilter::class,
        ]));

        return array_values(array_filter(
            $event->types,
            fn (string $class) => is_subclass_of($class, FilterInterface::class),
        ));
    }

    /**
     * Converts a value to an array for a payload, including the given extra attributes.
     *
     * @param  string[]  $extra
     * @return array<array-key, mixed>
     */
    public function toArray(mixed $object, array $extra = [], int $depth = 1): array
    {
        $arr = match (true) {
            $object instanceof Component => $object->toArray([], $extra, false),
            $object instanceof Arrayable => $object->toArray(),
            is_object($object) => get_object_vars($object),
            default => (array) $object,
        };

        $indexedExtra = [];

        foreach ($extra as $field) {
            $fieldParts = explode('.', $field, 2);

            if (isset($fieldParts[1])) {
                $indexedExtra[$fieldParts[0]][] = $fieldParts[1];
            }
        }

        foreach ($arr as $k => $v) {
            if ($v instanceof DateTimeInterface) {
                $arr[$k] = $v->format(DATE_ATOM);
            } elseif (is_array($v) || is_object($v)) {
                if ($depth >= $this->settings->maxDepth) {
                    unset($arr[$k]);
                } else {
                    $arr[$k] = $this->toArray($v, $indexedExtra[$k] ?? [], $depth + 1);
                }
            }
        }

        return $depth === 1 ? Security::redactIfSensitive('', $arr) : $arr;
    }

    /**
     * Returns the event’s subject: the first object it carries that is an instance of the webhook’s class (any object,
     * if the webhook has no class).
     */
    private function subject(Webhook $webhook, object $event): ?object
    {
        foreach (get_object_vars($event) as $value) {
            if (is_object($value) && (! $webhook->class || $value instanceof $webhook->class)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Returns the `event` variable for the webhook’s templates.
     *
     * Craft’s Twig sandbox doesn’t allow arbitrary event objects, so the event’s public properties are passed as an
     * array, along with its `name` and its `sender` (the subject), as in Craft 5.
     *
     * @return array<string, mixed>
     */
    private function twigEvent(object $event, ?object $subject): array
    {
        return [
            'name' => class_basename($event),
            'sender' => $subject,
            ...get_object_vars($event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPayload(Webhook $webhook, object $event, ?object $subject): array
    {
        $user = currentUserElement();
        $eventAttributes = $webhook->getEventAttributes();
        $data = [
            'time' => now()->format(DATE_ATOM),
            'user' => $user ? $this->toArray($user, $webhook->getUserAttributes()) : null,
            'name' => class_basename($event),
            'senderClass' => $subject ? $subject::class : null,
            'sender' => $subject ? $this->toArray($subject, $webhook->getSenderAttributes()) : null,
            'eventClass' => $event::class,
            'event' => [],
        ];

        foreach (get_object_vars($event) as $name => $value) {
            $data['event'][$name] = match (true) {
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                is_array($value) || is_object($value) => $this->toArray($value, $eventAttributes[$name] ?? []),
                default => $value,
            };
        }

        return $data;
    }

    private function lockKey(int $requestId): string
    {
        return "webhooks:request:$requestId";
    }
}
