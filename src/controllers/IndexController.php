<?php

namespace craft\webhooks\controllers;

use Craft;
use craft\web\Controller as BaseController;
use craft\webhooks\assets\index\IndexAsset;
use craft\webhooks\Group;
use craft\webhooks\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Webhooks Index Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class IndexController extends BaseController
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('accessPlugin-webhooks');

        return true;
    }

    /**
     * Shows the webhook index page
     *
     * @param int|null $groupId
     * @return Response
     */
    public function actionIndex(int $groupId = null): Response
    {
        Craft::$app->getView()->registerAssetBundle(IndexAsset::class);

        $manager = Plugin::getInstance()->getWebhookManager();

        return $this->renderTemplate('webhooks/_index', [
            'groups' => $manager->getAllGroups(),
            'webhooks' => $manager->getWebhooksByGroupId($groupId),
            'groupId' => $groupId,
        ]);
    }

    /**
     * Saves a webhook group
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionSaveGroup(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $manager = Plugin::getInstance()->getWebhookManager();

        $id = $request->getBodyParam('id');
        $name = $request->getRequiredBodyParam('name');
        $group = new Group(compact('id', 'name'));

        if (!$manager->saveGroup($group)) {
            throw new BadRequestHttpException('Invalid group');
        }

        if (!$id) {
            Craft::$app->getSession()->setNotice(Craft::t('webhooks', 'Group created.'));
        }

        return $this->asJson([
            'success' => true,
            'group' => $group,
        ]);
    }

    /**
     * Deletes a webhook group
     *
     * @return Response
     */
    public function actionDeleteGroup(): Response
    {
        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        Plugin::getInstance()->getWebhookManager()->deleteGroupById($id);
        Craft::$app->getSession()->setNotice(Craft::t('webhooks', 'Group deleted.'));
        return $this->asJson([
            'success' => true,
        ]);
    }
}
