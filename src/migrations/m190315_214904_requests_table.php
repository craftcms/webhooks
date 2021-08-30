<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * m190315_214904_requests_table migration.
 */
class m190315_214904_requests_table extends Migration
{
    /**
     * @inheritdoch
     */
    public function safeUp(): bool
    {
        // type => method
        $this->renameColumn('{{%webhooks}}', 'type', 'method');

        // Create the webhookrequests table
        $this->createTable('{{%webhookrequests}}', [
            'id' => $this->primaryKey(),
            'webhookId' => $this->integer(),
            'status' => $this->string()->notNull(),
            'attempts' => $this->tinyInteger(),
            'method' => $this->string(),
            'url' => $this->string(),
            'requestHeaders' => $this->text(),
            'requestBody' => $this->mediumText(),
            'responseStatus' => $this->smallInteger(),
            'responseHeaders' => $this->text(),
            'responseBody' => $this->text(),
            'responseTime' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateRequested' => $this->dateTime(),
            'uid' => $this->uid(),
        ]);

        $this->addForeignKey(null, '{{%webhookrequests}}', ['webhookId'], '{{%webhooks}}', ['id'], 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m190315_214904_requests_table cannot be reverted.\n";
        return false;
    }
}
