<?php

namespace craft\webhooks\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190724_202705_custom_headers migration.
 */
class m190724_202705_custom_headers extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%webhooks}}', 'headers', $this->text()->after('url'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190724_202705_custom_headers cannot be reverted.\n";
        return false;
    }
}
