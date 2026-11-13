<?php

declare(strict_types=1);

use CraftCms\Webhooks\Http\Controllers\ActivityController;
use CraftCms\Webhooks\Http\Controllers\GroupsController;
use CraftCms\Webhooks\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks')->name('webhooks.')->middleware('can:accessPlugin-webhooks')->group(function () {
    Route::get('', [WebhooksController::class, 'index'])->name('index');
    Route::get('new', [WebhooksController::class, 'create'])->name('create');
    Route::post('', [WebhooksController::class, 'store'])->name('store');
    Route::post('render-ui', [WebhooksController::class, 'renderUi'])->name('render-ui');
    Route::post('status', [WebhooksController::class, 'setStatus'])->name('status');
    Route::get('{webhookId}', [WebhooksController::class, 'edit'])->whereNumber('webhookId')->name('edit');
    Route::post('{webhookId}', [WebhooksController::class, 'update'])->whereNumber('webhookId')->name('update');
    Route::delete('{webhookId}', [WebhooksController::class, 'destroy'])->whereNumber('webhookId')->name('destroy');

    Route::get('groups/new', [GroupsController::class, 'create'])->name('groups.create');
    Route::post('groups', [GroupsController::class, 'store'])->name('groups.store');
    Route::get('groups/{groupId}', [GroupsController::class, 'edit'])->whereNumber('groupId')->name('groups.edit');
    Route::post('groups/{groupId}', [GroupsController::class, 'update'])->whereNumber('groupId')->name('groups.update');
    Route::delete('groups/{groupId}', [GroupsController::class, 'destroy'])->whereNumber('groupId')->name('groups.destroy');

    Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::delete('activity', [ActivityController::class, 'destroy'])->name('activity.destroy');
    Route::get('activity/{requestId}', [ActivityController::class, 'show'])->whereNumber('requestId')->name('activity.show');
    Route::post('activity/{requestId}/redeliver', [ActivityController::class, 'redeliver'])->whereNumber('requestId')->name('activity.redeliver');
});
