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
    public function safeUp()
    {
        $this->alterColumn('{{%webhookrequests}}', 'responseBody', $this->mediumText());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210401_212615_response_body_mediumtext cannot be reverted.\n";
        return false;
    }
}
