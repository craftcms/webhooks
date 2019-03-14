<?php

namespace craft\webhooks;

use Craft;
use craft\db\Query;
use craft\helpers\StringHelper;
use yii\base\InvalidArgumentException;

/**
 * Webhook Manager
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class WebhookManager
{
    // Groups
    // -------------------------------------------------------------------------

    /**
     * Returns all the webhook groups
     *
     * @return Group[]
     */
    public function getAllGroups(): array
    {
        $results = (new Query())
            ->select(['id', 'name'])
            ->from(['{{%webhookgroups}}'])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        $groups = [];
        $isMysql = Craft::$app->getDb()->getIsMysql();

        foreach ($results as $result) {
            if ($isMysql) {
                $result['name'] = html_entity_decode($result['name'], ENT_QUOTES | ENT_HTML5);
            }

            $groups[] = new Group($result);
        }

        return $groups;
    }

    /**
     * Saves a webhook group.
     *
     * @param Group $group
     * @param bool $runValidation
     * @return bool
     */
    public function saveGroup(Group $group, bool $runValidation = true): bool
    {
        if ($runValidation && !$group->validate()) {
            Craft::info('Webhook group not saved due to validation error.', __METHOD__);
            return false;
        }

        $db = Craft::$app->getDb();

        if ($db->getIsMysql()) {
            $name = StringHelper::encodeMb4($group->name);
        } else {
            $name = $group->name;
        }

        if ($group->id) {
            $db->createCommand()
                ->update('{{%webhookgroups}}', [
                    'name' => $name,
                ], [
                    'id' => $group->id,
                ])
                ->execute();
        } else {
            $db->createCommand()
                ->insert('{{%webhookgroups}}', [
                    'name' => $name,
                ])
                ->execute();

            $group->id = $db->getLastInsertID('{{%webhookgroups}}');
        }

        return true;
    }

    /**
     * Deletes a webhook group by its ID.
     *
     * @param int
     */
    public function deleteGroupById(int $id)
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%webhookgroups}}', [
                'id' => $id,
            ])
            ->execute();
    }

    // Webhooks
    // -------------------------------------------------------------------------

    /**
     * Returns all the webhooks
     *
     * @return Webhook[]
     */
    public function getAllWebhooks(): array
    {
        $results = $this->_createWebhookQuery()
            ->all();

        return $this->_createWebhooks($results);
    }

    /**
     * Returns all the webhooks
     *
     * @return Webhook[]
     */
    public function getEnabledWebhooks(): array
    {
        $results = $this->_createWebhookQuery()
            ->where(['enabled' => true])
            ->all();

        return $this->_createWebhooks($results);
    }

    /**
     * Returns all the webhooks in the given group ID.
     *
     * @param int|null $groupId The group ID, or null for ungrouped webhooks
     * @return Webhook[]
     */
    public function getWebhooksByGroupId(int $groupId = null): array
    {
        $results = $this->_createWebhookQuery()
            ->where(['groupId' => $groupId])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        return $this->_createWebhooks($results);
    }

    /**
     * Returns a webhook by its ID.
     *
     * @param int $id
     * @return Webhook
     * @throws InvalidArgumentException if $id is invalid
     */
    public function getWebhookById(int $id): Webhook
    {
        $result = $this->_createWebhookQuery()
            ->where(['id' => $id])
            ->one();

        if ($result === null) {
            throw new InvalidArgumentException('Invalid webhook ID: ' . $id);
        }

        return $this->_createWebhook($result);
    }

    /**
     * Saves a webhook.
     *
     * @param Webhook $webhook
     * @param bool $runValidation
     * @return bool
     */
    public function saveWebhook(Webhook $webhook, bool $runValidation = true): bool
    {
        if ($runValidation && !$webhook->validate()) {
            Craft::info('Webhook not saved due to validation error.', __METHOD__);
            return false;
        }

        $db = Craft::$app->getDb();

        if ($db->getIsMysql()) {
            $name = StringHelper::encodeMb4($webhook->name);
        } else {
            $name = $webhook->name;
        }

        $data = [
            'groupId' => $webhook->groupId,
            'enabled' => (bool)$webhook->enabled,
            'name' => $name,
            'class' => $webhook->class,
            'event' => $webhook->event,
            'type' => $webhook->type,
            'url' => $webhook->url,
            'userAttributes' => $webhook->userAttributes,
            'senderAttributes' => $webhook->senderAttributes,
            'eventAttributes' => $webhook->eventAttributes,
            'jsonPayloadTemplate' => $webhook->jsonPayloadTemplate
        ];

        if ($webhook->id) {
            $db->createCommand()
                ->update('{{%webhooks}}', $data, ['id' => $webhook->id])
                ->execute();
        } else {
            $db->createCommand()
                ->insert('{{%webhooks}}', $data)
                ->execute();
            $webhook->id = $db->getLastInsertID('{{%webhooks}}');
        }

        return true;
    }

    /**
     * Deletes a webhook by its ID.
     *
     * @param int $id
     */
    public function deleteWebhookById(int $id)
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%webhooks}}', ['id' => $id])
            ->execute();
    }

    /**
     * @return Query
     */
    private function _createWebhookQuery(): Query
    {
        return (new Query())
            ->select(['id', 'groupId', 'enabled', 'name', 'class', 'event', 'type', 'url', 'userAttributes', 'senderAttributes', 'eventAttributes', 'jsonPayloadTemplate'])
            ->from(['{{%webhooks}}']);
    }

    /**
     * @param array $result
     * @param bool|null $isMysql
     * @return Webhook
     */
    private function _createWebhook(array $result, bool $isMysql = null): Webhook
    {
        if ($isMysql ?? Craft::$app->getDb()->getIsMysql()) {
            $result['name'] = html_entity_decode($result['name'], ENT_QUOTES | ENT_HTML5);
        }

        return new Webhook($result);
    }

    /**
     * @param array
     * @return Webhook[]
     */
    private function _createWebhooks(array $results): array
    {
        $webhooks = [];
        $isMysql = Craft::$app->getDb()->getIsMysql();

        foreach ($results as $result) {
            $webhooks[] = $this->_createWebhook($result, $isMysql);
        }

        return $webhooks;
    }
}
