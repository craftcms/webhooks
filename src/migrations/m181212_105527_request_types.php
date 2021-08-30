<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * m181212_105527_request_types migration.
 */
class m181212_105527_request_types extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%webhooks}}', 'type', $this->string(10)->after('event'));
        $this->update('{{%webhooks}}', ['type' => 'post']);

        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            // (see https://github.com/yiisoft/yii2/issues/12077)
            $this->execute('alter table {{%webhooks}} alter column [[type]] set not null');
        } else {
            $this->alterColumn('{{%webhooks}}', 'type', $this->string(10)->notNull());
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m181212_105527_request_types cannot be reverted.\n";
        return false;
    }
}
