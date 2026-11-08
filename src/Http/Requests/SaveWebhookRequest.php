<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Http\Requests;

use Closure;
use CraftCms\Cms\Support\Env;
use CraftCms\UrlValidator\UrlValidationException;
use CraftCms\Webhooks\Webhooks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;
use Throwable;
use Twig\Error\SyntaxError;
use Twig\Sandbox\SecurityError;

use function CraftCms\Cms\renderSandboxedString;
use function CraftCms\Cms\t;

/**
 * Validates a submitted webhook.
 *
 * @since 4.0.0
 */
class SaveWebhookRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(Webhooks $webhooks): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('webhooks', 'name')->ignore($this->route('webhookId'))],
            'groupId' => ['nullable', 'integer', Rule::exists('webhookgroups', 'id')],
            'enabled' => ['boolean'],
            'event' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (! class_exists($value)) {
                    $fail(t('Class {value} doesn’t exist.', ['value' => $value], 'webhooks'));
                }
            }],
            'class' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (! class_exists($value) && ! interface_exists($value)) {
                    $fail(t('Class {value} doesn’t exist.', ['value' => $value], 'webhooks'));
                }
            }],
            'method' => ['required', Rule::in(['get', 'post', 'put'])],
            'url' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($webhooks) {
                // Environment variables and templates are validated again before each request is sent
                if (Env::parse($value) !== $value || str_contains($value, '{')) {
                    return;
                }

                try {
                    $webhooks->urlValidator()->validate($value);
                } catch (UrlValidationException $e) {
                    $fail(t('Invalid URL. {reason}', ['reason' => $e->getMessage()], 'webhooks'));
                }
            }],
            'debounceKeyFormat' => ['nullable', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['nullable', Rule::in(['', 'yes', 'no'])],
            'headers' => ['nullable', 'array'],
            'headers.*.name' => ['required', 'string', 'max:255'],
            'headers.*.value' => ['nullable', 'string'],
            'customPayload' => ['boolean'],
            'userAttributes' => ['nullable', 'string', $this->attributeListRule('/^[a-z]\w*(?:\.[a-z]\w*)*$/i')],
            'senderAttributes' => ['nullable', 'string', $this->attributeListRule('/^[a-z]\w*(?:\.[a-z]\w*)*$/i')],
            'eventAttributes' => ['nullable', 'string', $this->attributeListRule('/^[a-z]\w*\.[a-z]\w*(?:\.[a-z]\w*)*$/i')],
            'payloadTemplate' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) {
                // Render it in the sandbox, so syntax errors and disallowed tags, filters and functions are reported now
                try {
                    renderSandboxedString($value, ['event' => []]);
                } catch (SyntaxError|SecurityError $e) {
                    $fail((string) preg_replace('/ in "[^"]+"/', '', $e->getMessage()));
                } catch (Throwable) {
                    // Other errors can depend on the event
                }
            }],
        ];
    }

    /**
     * Returns the validated values as webhook attributes.
     *
     * @return array<string, mixed>
     */
    public function webhookAttributes(): array
    {
        $data = $this->validated();
        $customPayload = (bool) ($data['customPayload'] ?? false);
        $hasBody = $data['method'] !== 'get';
        $filters = [];

        foreach ($data['filters'] ?? [] as $class => $value) {
            if ($value === 'yes' || $value === 'no') {
                $filters[$class] = $value === 'yes';
            }
        }

        return [
            'name' => trim($data['name']),
            'groupId' => $data['groupId'] ?? null,
            'enabled' => (bool) ($data['enabled'] ?? false),
            'event' => trim($data['event'], ' \\'),
            'class' => ($class = trim((string) ($data['class'] ?? ''), ' \\')) !== '' ? $class : null,
            'filters' => $filters,
            'debounceKeyFormat' => ($data['debounceKeyFormat'] ?? null) ?: null,
            'method' => $data['method'],
            'url' => trim($data['url']),
            'headers' => array_values(array_map(
                fn (array $header) => ['name' => trim($header['name']), 'value' => (string) ($header['value'] ?? '')],
                $data['headers'] ?? [],
            )),
            'userAttributes' => $hasBody && ! $customPayload ? $this->normalizeAttributes($data['userAttributes'] ?? null) : null,
            'senderAttributes' => $hasBody && ! $customPayload ? $this->normalizeAttributes($data['senderAttributes'] ?? null) : null,
            'eventAttributes' => $hasBody && ! $customPayload ? $this->normalizeAttributes($data['eventAttributes'] ?? null) : null,
            'payloadTemplate' => $hasBody && $customPayload ? (($data['payloadTemplate'] ?? null) ?: '{}') : null,
        ];
    }

    #[Override]
    public function attributes(): array
    {
        return [
            'class' => t('Subject Class', category: 'webhooks'),
            'event' => t('Event', category: 'webhooks'),
            'name' => t('Name', category: 'webhooks'),
            'url' => t('URL', category: 'webhooks'),
        ];
    }

    private function attributeListRule(string $regex): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($regex) {
            foreach (preg_split('/[\r\n]+/', (string) $value) ?: [] as $line) {
                $line = trim($line);

                if ($line !== '' && ! preg_match($regex, $line)) {
                    $fail(t('{value} isn’t a valid attribute.', ['value' => $line], 'webhooks'));
                }
            }
        };
    }

    private function normalizeAttributes(?string $value): ?string
    {
        $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', (string) $value) ?: []));

        return $lines ? implode("\n", $lines) : null;
    }
}
