<?php

namespace craft\webhooks\controllers;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\webhooks\assets\edit\EditAsset;
use craft\webhooks\Plugin;
use craft\webhooks\Webhook;
use craft\webhooks\WebhookHelper;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Webhooks Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0
 */
class WebhooksController extends BaseController
{
    /**
     * Shows the edit page for a webhook.
     *
     * @param int|null $id
     * @param int|null $groupId
     * @param Webhook|null $webhook
     * @return Response
     * @throws NotFoundHttpException if $id is invalid
     */
    public function actionEdit(int $id = null, int $groupId = null, Webhook $webhook = null): Response
    {
        $manager = Plugin::getInstance()->getWebhookManager();

        if ($webhook === null) {
            if ($id !== null) {
                try {
                    $webhook = $manager->getWebhookById($id);
                } catch (InvalidArgumentException $e) {
                    throw new NotFoundHttpException($e->getMessage(), 0, $e);
                }
            } else {
                $webhook = new Webhook();
                if ($groupId !== null) {
                    $webhook->groupId = $groupId;
                }
            }
        }

        if ($webhook->id) {
            $title = trim($webhook->name) ?: Craft::t('webhooks', 'Edit Webhook');
        } else {
            $title = Craft::t('webhooks', 'Create a new webhook');
        }

        $crumbs = [
            [
                'label' => Craft::t('webhooks', 'Webhooks'),
                'url' => UrlHelper::url('webhooks')
            ]
        ];

        // Groups
        $groupOptions = [
            ['value' => null, 'label' => Craft::t('webhooks', '(Ungrouped)')]
        ];

        foreach ($manager->getAllGroups() as $group) {
            $groupOptions[] = ['value' => $group->id, 'label' => $group->name];

            if ($webhook->groupId && $webhook->groupId == $group->id) {
                $crumbs[] = [
                    'label' => $group->name,
                    'url' => UrlHelper::url("webhooks/group/{$group->id}")
                ];
            }
        }

        Craft::$app->getView()->registerAssetBundle(EditAsset::class);

        return $this->renderTemplate('webhooks/_manage/edit', compact(
            'groupOptions',
            'webhook',
            'title',
            'crumbs'
        ));
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $manager = Plugin::getInstance()->getWebhookManager();

        $id = $request->getBodyParam('id');

        if ($id) {
            try {
                $webhook = $manager->getWebhookById($id);
            } catch (InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage(), 0, $e);
            }
        } else {
            $webhook = new Webhook();
        }

        $attributes = $request->getBodyParams();
        if (ArrayHelper::remove($attributes, 'customPayload')) {
            $attributes['userAttributes'] = null;
            $attributes['senderAttributes'] = null;
            $attributes['eventAttributes'] = null;

            if (empty($attributes['payloadTemplate'])) {
                $attributes['payloadTemplate'] = '{}';
            }
        } else {
            $attributes['payloadTemplate'] = null;
        }
        $webhook->setAttributes($attributes);

        if (!Plugin::getInstance()->getWebhookManager()->saveWebhook($webhook)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $webhook->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('webhooks', 'Couldn’t save webhook.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'webhook' => $webhook,
            ]);
            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'webhook' => $webhook->toArray(),
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('webhooks', 'Webhook saved.'));
        return $this->redirectToPostedUrl($webhook);
    }

    /**
     * Deletes a webhook.
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');
        Plugin::getInstance()->getWebhookManager()->deleteWebhookById($id);
        return $this->redirectToPostedUrl();
    }

    /**
     * Returns the available sender classes.
     *
     * @return Response
     */
    public function actionClassSuggestions(): Response
    {
        return $this->asJson([
            'classes' => WebhookHelper::classSuggestions(),
        ]);
    }

    /**
     * Returns the available events for a component class.
     *
     * @return Response
     */
    public function actionEventSuggestions(): Response
    {
        $senderClass = Craft::$app->getRequest()->getRequiredBodyParam('senderClass');

        return $this->asJson([
            'events' => WebhookHelper::eventSuggestions($senderClass),
        ]);
    }
}
