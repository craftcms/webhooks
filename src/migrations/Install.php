<?php

namespace craft\webhooks\migrations;

use craft\db\Migration;

/**
 * Install migration.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
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
            'type' => $this->string(10)->notNull(),
            'url' => $this->string()->notNull(),
            'userAttributes' => $this->text(),
            'senderAttributes' => $this->text(),
            'eventAttributes' => $this->text(),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(null, '{{%webhooks}}', ['enabled']);
        $this->createIndex(null, '{{%webhooks}}', ['groupId', 'name']);
        $this->createIndex(null, '{{%webhooks}}', ['name'], true);
        $this->addForeignKey(null, '{{%webhooks}}', ['groupId'], '{{%webhookgroups}}', ['id'], 'SET NULL');
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        // Drop the DB table
        $this->dropTableIfExists('{{%webhooks}}');
        $this->dropTableIfExists('{{%webhookgroups}}');
    }
}
