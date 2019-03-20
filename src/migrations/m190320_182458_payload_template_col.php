<?php

namespace craft\webhooks\migrations;

use Craft;
use craft\db\Migration;

/**
 * m190320_182458_payload_template_col migration.
 */
class m190320_182458_payload_template_col extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%webhooks}}', 'payloadTemplate')) {
            $this->addColumn('{{%webhooks}}', 'payloadTemplate', $this->mediumText()->after('eventAttributes'));
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190320_182458_payload_template_col cannot be reverted.\n";
        return false;
    }
}
