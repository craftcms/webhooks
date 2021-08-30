<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * Install migration.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Create the webhookgroups table
        $this->createTable('{{%webhookgroups}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Create the webhooks table
        $this->createTable('{{%webhooks}}', [
            'id' => $this->primaryKey(),
            'groupId' => $this->integer()->null(),
            'name' => $this->string()->notNull(),
            'class' => $this->string()->notNull(),
            'event' => $this->string()->notNull(),
            'filters' => $this->text(),
            'debounceKeyFormat' => $this->string(),
            'method' => $this->string(10)->notNull(),
            'url' => $this->string()->notNull(),
            'headers' => $this->text(),
            'userAttributes' => $this->text(),
            'senderAttributes' => $this->text(),
            'eventAttributes' => $this->text(),
            'payloadTemplate' => $this->mediumText(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // Create the webhookrequests table
        $this->createTable('{{%webhookrequests}}', [
            'id' => $this->primaryKey(),
            'webhookId' => $this->integer(),
            'debounceKey' => $this->string(),
            'status' => $this->string()->notNull(),
            'attempts' => $this->tinyInteger(),
            'method' => $this->string(),
            'url' => $this->string(),
            'requestHeaders' => $this->text(),
            'requestBody' => $this->mediumText(),
            'responseStatus' => $this->smallInteger(),
            'responseHeaders' => $this->text(),
            'responseBody' => $this->mediumText(),
            'responseTime' => $this->integer(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateRequested' => $this->dateTime(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%webhooks}}', ['enabled']);
        $this->createIndex(null, '{{%webhooks}}', ['groupId', 'name']);
        $this->createIndex(null, '{{%webhooks}}', ['name'], true);
        $this->createIndex(null, '{{%webhookrequests}}', ['debounceKey', 'status']);
        $this->addForeignKey(null, '{{%webhooks}}', ['groupId'], '{{%webhookgroups}}', ['id'], 'SET NULL');
        $this->addForeignKey(null, '{{%webhookrequests}}', ['webhookId'], '{{%webhooks}}', ['id'], 'SET NULL');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        // Drop the DB table
        $this->dropTableIfExists('{{%webhookrequests}}');
        $this->dropTableIfExists('{{%webhooks}}');
        $this->dropTableIfExists('{{%webhookgroups}}');
    }
}
