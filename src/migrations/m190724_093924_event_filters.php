<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * m190724_093924_event_filters migration.
 */
class m190724_093924_event_filters extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%webhooks}}', 'filters', $this->text()->after('event'));
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m190724_093924_event_filters cannot be reverted.\n";
        return false;
    }
}
