<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * m210929_023246_increase_webhook_url_size migration.
 */
class m210929_023246_increase_webhook_url_size extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->alterColumn('{{%webhooks}}', 'url', 'text');
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->alterColumn('{{%webhooks}}', 'url', 'string');
        return true;
    }
}
