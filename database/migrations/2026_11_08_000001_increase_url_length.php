<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets webhook URLs be longer than 255 characters, such as ones with long tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->mediumText('url')->change();
        });

        Schema::table('webhookrequests', function (Blueprint $table) {
            $table->mediumText('url')->nullable()->change();
        });
    }
};
