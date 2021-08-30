<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * m210830_202902_status_index migration.
 */
class m210830_202902_status_index extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createIndex(null, '{{%webhookrequests}}', ['status', 'dateCreated']);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m210830_202902_status_index cannot be reverted.\n";
        return false;
    }
}
