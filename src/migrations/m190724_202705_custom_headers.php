<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * m190724_202705_custom_headers migration.
 */
class m190724_202705_custom_headers extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%webhooks}}', 'headers', $this->text()->after('url'));
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m190724_202705_custom_headers cannot be reverted.\n";
        return false;
    }
}
