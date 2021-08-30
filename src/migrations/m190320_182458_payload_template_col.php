<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * m190320_182458_payload_template_col migration.
 */
class m190320_182458_payload_template_col extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%webhooks}}', 'payloadTemplate')) {
            $this->addColumn('{{%webhooks}}', 'payloadTemplate', $this->mediumText()->after('eventAttributes'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m190320_182458_payload_template_col cannot be reverted.\n";
        return false;
    }
}
