<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Database\Migrations;

use CraftCms\Cms\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the Webhooks tables.
 *
 * @since 1.0.0
 */
class Install extends Migration
{
    public function up(): void
    {
        $this->dropTables();

        Schema::create('webhookgroups', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name');
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('groupId')->nullable();
            $table->string('name');
            $table->string('class')->nullable();
            $table->string('event');
            $table->text('filters')->nullable();
            $table->string('debounceKeyFormat')->nullable();
            $table->string('method', 10);
            $table->string('url');
            $table->text('headers')->nullable();
            $table->text('userAttributes')->nullable();
            $table->text('senderAttributes')->nullable();
            $table->text('eventAttributes')->nullable();
            $table->mediumText('payloadTemplate')->nullable();
            $table->boolean('enabled')->default(true);
            $table->dateTime('dateCreated');
            $table->dateTime('dateUpdated');
            $table->char('uid', 36)->default('0');

            $table->index(['enabled']);
            $table->index(['groupId', 'name']);
            $table->unique(['name']);
            $table->foreign('groupId')->references('id')->on('webhookgroups')->nullOnDelete();
        });

        Schema::create('webhookrequests', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('webhookId')->nullable();
            $table->string('debounceKey')->nullable();
            $table->string('status');
            $table->tinyInteger('attempts')->nullable();
            $table->string('method')->nullable();
            $table->string('url')->nullable();
            $table->text('requestHeaders')->nullable();
            $table->mediumText('requestBody')->nullable();
            $table->smallInteger('responseStatus')->nullable();
            $table->text('responseHeaders')->nullable();
            $table->mediumText('responseBody')->nullable();
            $table->integer('responseTime')->nullable();
            $table->dateTime('dateCreated');
            $table->dateTime('dateRequested')->nullable();
            $table->char('uid', 36)->default('0');

            $table->index(['debounceKey', 'status']);
            $table->index(['status', 'dateCreated']);
            $table->foreign('webhookId')->references('id')->on('webhooks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropTables();
    }

    private function dropTables(): void
    {
        Schema::dropIfExists('webhookrequests');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('webhookgroups');
    }
}
