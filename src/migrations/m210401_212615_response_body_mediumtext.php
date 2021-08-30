<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * m210401_212615_response_body_mediumtext migration.
 */
class m210401_212615_response_body_mediumtext extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->alterColumn('{{%webhookrequests}}', 'responseBody', $this->mediumText());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m210401_212615_response_body_mediumtext cannot be reverted.\n";
        return false;
    }
}
