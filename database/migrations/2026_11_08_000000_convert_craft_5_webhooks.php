<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Element\BulkOp\Events\BulkOpCompleted;
use CraftCms\Cms\Element\BulkOp\Events\BulkOpStarting;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Events\CanonicalChangesMerged;
use CraftCms\Cms\Element\Events\CanonicalChangesMerging;
use CraftCms\Cms\Element\Events\ElementActionPerformed;
use CraftCms\Cms\Element\Events\ElementActionPerforming;
use CraftCms\Cms\Element\Events\ElementCachesInvalidated;
use CraftCms\Cms\Element\Events\ElementDeleted;
use CraftCms\Cms\Element\Events\ElementDeletedForSite;
use CraftCms\Cms\Element\Events\ElementDeleting;
use CraftCms\Cms\Element\Events\ElementDeletingForSite;
use CraftCms\Cms\Element\Events\ElementLifecycleDeleted;
use CraftCms\Cms\Element\Events\ElementLifecycleDeleting;
use CraftCms\Cms\Element\Events\ElementLifecyclePropagated;
use CraftCms\Cms\Element\Events\ElementLifecycleRestored;
use CraftCms\Cms\Element\Events\ElementLifecycleRestoring;
use CraftCms\Cms\Element\Events\ElementLifecycleSaved;
use CraftCms\Cms\Element\Events\ElementLifecycleSaving;
use CraftCms\Cms\Element\Events\ElementMovedInStructure;
use CraftCms\Cms\Element\Events\ElementMovingInStructure;
use CraftCms\Cms\Element\Events\ElementPropagated;
use CraftCms\Cms\Element\Events\ElementPropagating;
use CraftCms\Cms\Element\Events\ElementResaved;
use CraftCms\Cms\Element\Events\ElementResaving;
use CraftCms\Cms\Element\Events\ElementRestored;
use CraftCms\Cms\Element\Events\ElementRestoring;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Element\Events\ElementSaving;
use CraftCms\Cms\Element\Events\ElementsEagerLoading;
use CraftCms\Cms\Element\Events\ElementSearchIndexUpdating;
use CraftCms\Cms\Element\Events\ElementSlugAndUriUpdated;
use CraftCms\Cms\Element\Events\ElementSlugAndUriUpdating;
use CraftCms\Cms\Element\Events\ElementsMerged;
use CraftCms\Cms\Element\Events\ElementsPropagated;
use CraftCms\Cms\Element\Events\ElementsPropagating;
use CraftCms\Cms\Element\Events\ElementsResaved;
use CraftCms\Cms\Element\Events\ElementsResaving;
use CraftCms\Cms\Element\Events\SetElementUri;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Elements\ContentBlock;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\Events\EmailVerified;
use CraftCms\Cms\User\Events\UserActivated;
use CraftCms\Cms\User\Events\UserActivating;
use CraftCms\Cms\User\Events\UserDeactivated;
use CraftCms\Cms\User\Events\UserDeactivating;
use CraftCms\Cms\User\Events\UserEmailVerifying;
use CraftCms\Cms\User\Events\UserLocked;
use CraftCms\Cms\User\Events\UserSuspended;
use CraftCms\Cms\User\Events\UserSuspending;
use CraftCms\Cms\User\Events\UserUnlocked;
use CraftCms\Cms\User\Events\UserUnlocking;
use CraftCms\Cms\User\Events\UserUnsuspended;
use CraftCms\Cms\User\Events\UserUnsuspending;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Converts Craft 5 webhooks to Craft 6 events.
 *
 * Craft 5 webhooks listened for a Yii event (a sender class and event name). Each one becomes a Laravel event class,
 * with the sender class kept as the webhook’s subject class where it was an element type. Webhooks whose event has no
 * Craft 6 equivalent here are disabled and left unchanged, so they can be fixed from the control panel.
 */
return new class extends Migration
{
    /** Craft 5 element classes, and their Craft 6 replacements. */
    private const array ELEMENT_CLASSES = [
        'craft\base\Element' => Element::class,
        'craft\base\ElementInterface' => ElementInterface::class,
        'craft\elements\Address' => Address::class,
        'craft\elements\Asset' => Asset::class,
        'craft\elements\ContentBlock' => ContentBlock::class,
        'craft\elements\Entry' => Entry::class,
        'craft\elements\User' => User::class,
    ];

    /** Events triggered by element classes, and the Craft 6 events that replace them. */
    private const array ELEMENT_EVENTS = [
        'beforeSave' => ElementLifecycleSaving::class,
        'afterSave' => ElementLifecycleSaved::class,
        'afterPropagate' => ElementLifecyclePropagated::class,
        'beforeDelete' => ElementLifecycleDeleting::class,
        'afterDelete' => ElementLifecycleDeleted::class,
        'beforeRestore' => ElementLifecycleRestoring::class,
        'afterRestore' => ElementLifecycleRestored::class,
        'beforeMoveInStructure' => ElementMovingInStructure::class,
        'afterMoveInStructure' => ElementMovedInStructure::class,
    ];

    /** Events triggered by Craft 5 services, and the Craft 6 events that replace them. */
    private const array SERVICE_EVENTS = [
        'craft\services\Elements' => [
            'afterBulkOp' => BulkOpCompleted::class,
            'afterDeleteElement' => ElementDeleted::class,
            'afterDeleteForSite' => ElementDeletedForSite::class,
            'afterMergeCanonical' => CanonicalChangesMerged::class,
            'afterMergeElements' => ElementsMerged::class,
            'afterPerformAction' => ElementActionPerformed::class,
            'afterPropagateElement' => ElementPropagated::class,
            'afterPropagateElements' => ElementsPropagated::class,
            'afterResaveElement' => ElementResaved::class,
            'afterResaveElements' => ElementsResaved::class,
            'afterRestoreElement' => ElementRestored::class,
            'afterSaveElement' => ElementSaved::class,
            'afterUpdateSlugAndUri' => ElementSlugAndUriUpdated::class,
            'beforeBulkOp' => BulkOpStarting::class,
            'beforeDeleteElement' => ElementDeleting::class,
            'beforeDeleteForSite' => ElementDeletingForSite::class,
            'beforeEagerLoadElements' => ElementsEagerLoading::class,
            'beforeMergeCanonical' => CanonicalChangesMerging::class,
            'beforePerformAction' => ElementActionPerforming::class,
            'beforePropagateElement' => ElementPropagating::class,
            'beforePropagateElements' => ElementsPropagating::class,
            'beforeResaveElement' => ElementResaving::class,
            'beforeResaveElements' => ElementsResaving::class,
            'beforeRestoreElement' => ElementRestoring::class,
            'beforeSaveElement' => ElementSaving::class,
            'beforeUpdateSearchIndex' => ElementSearchIndexUpdating::class,
            'beforeUpdateSlugAndUri' => ElementSlugAndUriUpdating::class,
            'invalidateCaches' => ElementCachesInvalidated::class,
            'setElementUri' => SetElementUri::class,
        ],
        'craft\services\Users' => [
            'afterActivateUser' => UserActivated::class,
            'afterDeactivateUser' => UserDeactivated::class,
            'afterLockUser' => UserLocked::class,
            'afterSuspendUser' => UserSuspended::class,
            'afterUnlockUser' => UserUnlocked::class,
            'afterUnsuspendUser' => UserUnsuspended::class,
            'afterVerifyEmail' => EmailVerified::class,
            'beforeActivateUser' => UserActivating::class,
            'beforeDeactivateUser' => UserDeactivating::class,
            'beforeSuspendUser' => UserSuspending::class,
            'beforeUnlockUser' => UserUnlocking::class,
            'beforeUnsuspendUser' => UserUnsuspending::class,
            'beforeVerifyEmail' => UserEmailVerifying::class,
        ],
    ];

    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('class')->nullable()->change();
        });

        foreach (DB::table('webhooks')->get() as $webhook) {
            $data = ['filters' => $this->convertFilters($webhook->filters)];
            $converted = $this->convertEvent((string) $webhook->class, (string) $webhook->event);

            if ($converted === null) {
                $data['enabled'] = false;
                Log::warning("Webhook “{$webhook->name}” was disabled, because its {$webhook->class}::{$webhook->event} event has no Craft 6 equivalent. Choose a new event for it in Webhooks → Manage Webhooks.");
            } else {
                [$data['class'], $data['event']] = $converted;
            }

            DB::table('webhooks')->where('id', $webhook->id)->update($data);
        }
    }

    /**
     * @return array{0: string|null, 1: string}|null The subject class and event class
     */
    private function convertEvent(string $class, string $event): ?array
    {
        $class = ltrim($class, '\\');

        if (isset(self::SERVICE_EVENTS[$class][$event])) {
            return [null, self::SERVICE_EVENTS[$class][$event]];
        }

        if (isset(self::ELEMENT_CLASSES[$class], self::ELEMENT_EVENTS[$event])) {
            return [self::ELEMENT_CLASSES[$class], self::ELEMENT_EVENTS[$event]];
        }

        return null;
    }

    private function convertFilters(?string $filters): ?string
    {
        if (! $filters) {
            return $filters;
        }

        $converted = [];

        foreach (Json::decode($filters) as $class => $value) {
            if (str_starts_with($class, 'craft\\webhooks\\filters\\')) {
                $class = 'CraftCms\\Webhooks\\Filters\\'.substr($class, strlen('craft\\webhooks\\filters\\'));
            }

            $converted[$class] = $value;
        }

        return Json::encode($converted);
    }
};
