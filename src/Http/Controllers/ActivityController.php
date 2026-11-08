<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Http\Controllers;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Webhooks\Models\WebhookRequest;
use CraftCms\Webhooks\Webhooks;
use DateTimeInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

/**
 * Lists sent and pending webhook requests, shows their details, and redelivers them.
 *
 * @since 2.0.0
 */
readonly class ActivityController
{
    use RespondsWithFlash;

    public function __construct(
        private Webhooks $webhooks,
    ) {}

    public function index(Request $request): CpScreenResponse
    {
        $paginator = WebhookRequest::query()
            ->with('webhook')
            ->orderByDesc('id')
            ->paginate(100);

        $table = Table::make('webhook-requests')
            ->columns([
                ['key' => 'request', 'label' => t('Request', category: 'webhooks')],
                ['key' => 'status', 'label' => t('Status', category: 'webhooks')],
                ['key' => 'attempts', 'label' => t('Attempts', category: 'webhooks')],
                ['key' => 'date', 'label' => t('Date', category: 'webhooks')],
                ['key' => 'time', 'label' => t('Time', category: 'webhooks')],
                ['key' => 'webhook', 'label' => t('Webhook', category: 'webhooks')],
            ])
            ->rows(collect($paginator->items())->map(fn (WebhookRequest $webhookRequest) => [
                'id' => $webhookRequest->id,
                'request' => [
                    'label' => $this->label($webhookRequest),
                    'url' => route('webhooks.activity.show', ['requestId' => $webhookRequest->id]),
                ],
                'status' => $this->statusLabel($webhookRequest),
                'attempts' => $webhookRequest->attempts,
                'date' => ['html' => Html::tag(
                    'time',
                    Html::encode($this->date($webhookRequest)),
                    ['datetime' => ($webhookRequest->dateRequested ?? $webhookRequest->dateCreated)->format(DateTimeInterface::ATOM)],
                )],
                'time' => $webhookRequest->responseTime !== null ? "{$webhookRequest->responseTime}ms" : null,
                'webhook' => $webhookRequest->webhook
                    ? ['label' => $webhookRequest->webhook->name, 'url' => route('webhooks.edit', ['webhookId' => $webhookRequest->webhook->id])]
                    : null,
            ]))
            ->pagination([
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ])
            ->emptyMessage(t('No webhook requests have been sent yet.', category: 'webhooks'))
            ->bulkDeletable(route('webhooks.activity.destroy'));

        return new CpScreenResponse()
            ->title(t('Activity', category: 'webhooks'))
            ->selectedSubnavItem('activity')
            ->ui(Ui::make([$table]));
    }

    public function show(int $requestId): CpScreenResponse
    {
        $webhookRequest = WebhookRequest::query()->with('webhook')->findOrFail($requestId);
        $readOnly = fn (string $label, string $path, bool $multiline = false) => Field::make(
            $label,
            ($multiline ? Textarea::make($path)->rows(12) : Text::make($path))->monospace()->mode(ControlMode::ReadOnly),
        );

        return new CpScreenResponse()
            ->title($this->label($webhookRequest))
            ->selectedSubnavItem('activity')
            ->addCrumb(t('Activity', category: 'webhooks'), route('webhooks.activity.index'))
            ->submitButtonLabel(t('Redeliver', category: 'webhooks'))
            ->ui(
                Ui::make([
                    $readOnly(t('URL', category: 'webhooks'), 'url'),
                    $readOnly(t('Status', category: 'webhooks'), 'status'),
                    $readOnly(t('Attempts', category: 'webhooks'), 'attempts'),
                    $readOnly(t('Date', category: 'webhooks'), 'date'),
                    $readOnly(t('Time', category: 'webhooks'), 'time'),
                    $readOnly(t('Request', category: 'webhooks'), 'request', true),
                    $readOnly(t('Response', category: 'webhooks'), 'response', true),
                ]),
                new UiContext(values: [
                    'url' => strtoupper($webhookRequest->method).' '.$webhookRequest->url,
                    'status' => $this->statusLabel($webhookRequest),
                    'attempts' => (string) ($webhookRequest->attempts ?? 0),
                    'date' => $this->date($webhookRequest),
                    'time' => $webhookRequest->responseTime !== null ? "{$webhookRequest->responseTime}ms" : '',
                    'request' => $this->message($webhookRequest->requestHeaders, $webhookRequest->requestBody),
                    'response' => $this->message($webhookRequest->responseHeaders, $webhookRequest->responseBody),
                ]),
                ['submit' => [
                    'method' => 'post',
                    'url' => route('webhooks.activity.redeliver', ['requestId' => $webhookRequest->id]),
                ]],
            );
    }

    /**
     * Sends the request again, right away.
     */
    public function redeliver(int $requestId): Response
    {
        $success = $this->webhooks->send($requestId);

        return $success
            ? $this->asSuccess(t('Webhook redelivered.', category: 'webhooks'), redirect: route('webhooks.activity.show', ['requestId' => $requestId]))
            : $this->asFailure(t('The webhook request failed.', category: 'webhooks'));
    }

    /**
     * Deletes the selected requests.
     */
    public function destroy(Request $request): Response
    {
        $data = $request->validate([
            'ids' => ['required_without:id', 'array'],
            'ids.*' => ['integer'],
            'id' => ['nullable', 'integer'],
        ]);

        WebhookRequest::query()->whereKey($data['ids'] ?? [$data['id']])->delete();

        return $this->asSuccess(t('Requests deleted.', category: 'webhooks'));
    }

    /**
     * Returns the request’s method and host. The host comes from the parsed URL, so it’s shown for URLs set to an
     * environment variable too.
     */
    private function label(WebhookRequest $webhookRequest): string
    {
        $url = (string) Env::parse($webhookRequest->url);

        return strtoupper($webhookRequest->method).' '.(parse_url($url, PHP_URL_HOST) ?: $webhookRequest->url);
    }

    private function date(WebhookRequest $webhookRequest): string
    {
        return I18N::getFormatter()->asDatetime(
            $webhookRequest->dateRequested ?? $webhookRequest->dateCreated,
            Locale::LENGTH_SHORT,
            withTimeZone: true,
        );
    }

    private function statusLabel(WebhookRequest $webhookRequest): string
    {
        return match (true) {
            $webhookRequest->responseStatus !== null => (string) $webhookRequest->responseStatus,
            $webhookRequest->status->value === 'done' => t('Failed', category: 'webhooks'),
            default => t(ucfirst($webhookRequest->status->value), category: 'webhooks'),
        };
    }

    /**
     * Formats headers and a body like an HTTP message, pretty-printing JSON bodies.
     *
     * @param  array<string, string|string[]>|null  $headers
     */
    private function message(?array $headers, ?string $body): string
    {
        $lines = [];
        $isJson = false;

        foreach ($headers ?? [] as $name => $values) {
            foreach ((array) $values as $value) {
                $lines[] = "$name: $value";
                $isJson = $isJson || (strtolower($name) === 'content-type' && str_starts_with(strtolower($value), 'application/json'));
            }
        }

        if ($body !== null && $body !== '') {
            if ($isJson && is_array($decoded = Json::decodeIfJson($body))) {
                $body = Json::encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            $lines[] = '';
            $lines[] = $body;
        }

        return implode("\n", $lines);
    }
}
