<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Enums;

/**
 * The status of a webhook request.
 *
 * @since 4.0.0
 */
enum RequestStatus: string
{
    /** Waiting to be sent. */
    case Pending = 'pending';

    /** Being sent. */
    case Requested = 'requested';

    /** Sent, successfully or not. */
    case Done = 'done';
}
