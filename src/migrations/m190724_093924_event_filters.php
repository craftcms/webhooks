<?php

namespace craft\webhooks\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190724_093924_event_filters migration.
 */
class m190724_093924_event_filters extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%webhooks}}', 'filters', $this->text()->after('event'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190724_093924_event_filters cannot be reverted.\n";
        return false;
    }
}
