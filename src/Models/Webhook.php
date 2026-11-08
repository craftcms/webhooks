<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * A webhook: the event that triggers it, and the request it sends.
 *
 * @property int $id
 * @property int|null $groupId
 * @property bool $enabled
 * @property string $name
 * @property class-string|null $class The class the event’s subject (e.g. its element) must be an instance of
 * @property string $event The event class that triggers the webhook
 * @property array<class-string, bool> $filters
 * @property string|null $debounceKeyFormat
 * @property string $method
 * @property string $url
 * @property list<array{name: string, value: string}> $headers
 * @property string|null $userAttributes
 * @property string|null $senderAttributes
 * @property string|null $eventAttributes
 * @property string|null $payloadTemplate
 * @property-read Group|null $group
 *
 * @since 1.0.0
 */
class Webhook extends BaseModel
{
    use HasUid;

    #[Override]
    protected $table = 'webhooks';

    #[Override]
    protected $attributes = [
        'enabled' => true,
        'method' => 'post',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'groupId' => 'integer',
            'enabled' => 'boolean',
            'filters' => 'array',
            'headers' => 'array',
        ];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'groupId');
    }

    /** @return HasMany<WebhookRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(WebhookRequest::class, 'webhookId');
    }

    /** @param Builder<self> $query */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    /**
     * Whether the webhook sends a request body.
     */
    public function hasBody(): bool
    {
        return in_array($this->method, ['post', 'put'], true);
    }

    /**
     * @return string[]
     */
    public function getUserAttributes(): array
    {
        return self::splitAttributes($this->userAttributes);
    }

    /**
     * @return string[]
     */
    public function getSenderAttributes(): array
    {
        return self::splitAttributes($this->senderAttributes);
    }

    /**
     * Returns the extra event attributes, indexed by the event property they belong to.
     *
     * @return array<string, string[]>
     */
    public function getEventAttributes(): array
    {
        $attributes = [];

        foreach (self::splitAttributes($this->eventAttributes) as $attribute) {
            [$property, $attribute] = explode('.', $attribute, 2);
            $attributes[$property][] = $attribute;
        }

        return $attributes;
    }

    /**
     * @return string[]
     */
    public static function splitAttributes(?string $attributes): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $attributes ?? '') ?: [])));
    }
}
