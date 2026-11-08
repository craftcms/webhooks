<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Models;

use Carbon\CarbonInterface;
use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use CraftCms\Webhooks\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * A queued or sent webhook request, as listed on the Activity page.
 *
 * The URL and header values are stored as rendered, but before environment variables are parsed, so secrets referenced
 * with `$VARIABLE_NAME` aren’t saved in the database.
 *
 * @property int $id
 * @property int|null $webhookId
 * @property string|null $debounceKey
 * @property RequestStatus $status
 * @property int|null $attempts
 * @property string $method
 * @property string $url
 * @property array<string, string|string[]>|null $requestHeaders
 * @property string|null $requestBody
 * @property int|null $responseStatus
 * @property array<string, string[]>|null $responseHeaders
 * @property string|null $responseBody
 * @property int|null $responseTime
 * @property CarbonInterface $dateCreated
 * @property CarbonInterface|null $dateRequested
 * @property-read Webhook|null $webhook
 *
 * @since 4.0.0
 */
class WebhookRequest extends BaseModel
{
    use HasUid;

    #[Override]
    protected $table = 'webhookrequests';

    /** The table has no `dateUpdated` column. */
    public const ?string UPDATED_AT = null;

    #[Override]
    protected function casts(): array
    {
        return [
            'webhookId' => 'integer',
            'status' => RequestStatus::class,
            'attempts' => 'integer',
            'requestHeaders' => 'array',
            'responseStatus' => 'integer',
            'responseHeaders' => 'array',
            'responseTime' => 'integer',
            'dateRequested' => 'datetime',
        ];
    }

    /** @return BelongsTo<Webhook, $this> */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class, 'webhookId');
    }
}
