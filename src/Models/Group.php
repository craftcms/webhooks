<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Cms\Shared\Concerns\HasUid;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * A webhook group.
 *
 * @property int $id
 * @property string $name
 *
 * @since 1.0.0
 */
class Group extends BaseModel
{
    use HasUid;

    #[Override]
    protected $table = 'webhookgroups';

    /** @return HasMany<Webhook, $this> */
    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class, 'groupId');
    }
}
