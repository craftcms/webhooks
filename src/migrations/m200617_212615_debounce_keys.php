<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * m200617_212615_debounce_keys migration.
 */
class m200617_212615_debounce_keys extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%webhooks}}', 'debounceKeyFormat', $this->string()->after('filters'));
        $this->addColumn('{{%webhookrequests}}', 'debounceKey', $this->string()->after('webhookId'));
        $this->createIndex(null, '{{%webhookrequests}}', ['debounceKey', 'status']);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m200617_212615_debounce_keys cannot be reverted.\n";
        return false;
    }
}
