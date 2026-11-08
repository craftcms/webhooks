<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * m240901_112003_url_length migration.
 */
class m240901_112003_url_length extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->alterColumn('{{%webhooks}}', 'url', $this->mediumText()->notNull());
        $this->alterColumn('{{%webhookrequests}}', 'url', $this->mediumText());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->alterColumn('{{%webhooks}}', 'url', $this->string()->notNull());
        $this->alterColumn('{{%webhookrequests}}', 'url', $this->string());

        return true;
    }
}
