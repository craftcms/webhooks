<?php

namespace craft\webhooks\controllers;

use Craft;
use craft\db\Paginator;
use craft\db\Query;
use craft\helpers\Json;
use craft\web\twig\variables\Paginate;
use craft\webhooks\assets\activity\ActivityAsset;
use craft\webhooks\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Activity Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0.0
 */
class ActivityController extends BaseController
{
    /**
     * Shows the webhook activity page
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        Craft::$app->getView()->registerAssetBundle(ActivityAsset::class);

        $query = (new Query())
            ->select(['r.*', 'w.name'])
            ->from(['{{%webhookrequests}} r'])
            ->leftJoin('{{%webhooks}} w', '[[w.id]] = [[r.webhookId]]')
            ->orderBy(['id' => SORT_DESC]);

        $paginator = new Paginator($query, [
            'currentPage' => Craft::$app->getRequest()->getPageNum(),
        ]);

        $requests = $paginator->getPageResults();
        foreach ($requests as &$request) {
            $request['host'] = parse_url($request['url'], PHP_URL_HOST);
        }

        return $this->renderTemplate('webhooks/_activity/index', [
            'requests' => $requests,
            'pageInfo' => Paginate::create($paginator),
        ]);
    }

    /**
     * Clears the requests table.
     *
     * @return Response
     * @since 2.3.0
     */
    public function actionClear(): Response
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%webhookrequests}}', ['status' => Plugin::STATUS_DONE])
            ->execute();

        return $this->redirect('webhooks/activity');
    }

    /**
     * Returns request details modal HTML.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionDetails(): Response
    {
        $requestId = Craft::$app->getRequest()->getRequiredBodyParam('requestId');
        $request = Plugin::getInstance()->getRequestData($requestId);

        if ($request['requestHeaders'] && $request['requestBody'] && $this->_isJson($request['requestHeaders'])) {
            $request['requestBody'] = $this->_prettyJson($request['requestBody']);
        }
        if ($request['responseHeaders'] && $request['responseBody'] && $this->_isJson($request['responseHeaders'])) {
            $request['responseBody'] = $this->_prettyJson($request['responseBody']);
        }

        return $this->asJson([
            'html' => Craft::$app->getView()->renderTemplate('webhooks/_activity/details', [
                'request' => $request,
            ]),
        ]);
    }

    /**
     * Returns request details modal HTML.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionRedeliver(): Response
    {
        $requestId = Craft::$app->getRequest()->getRequiredBodyParam('requestId');
        Plugin::getInstance()->sendRequest($requestId);

        return $this->runAction('details');
    }

    /**
     * Returns whether a Content-Type header exists in the given list of headers, and is set to application/json.
     *
     * @param array $headers
     * @return bool
     */
    private function _isJson(array $headers): bool
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'content-type') {
                $value = is_array($value) ? reset($value) : $value;
                return strtolower(trim($value)) === 'application/json';
            }
        }
        return false;
    }

    /**
     * Prettifies a JSON blob.
     *
     * @param string $json
     * @return string
     */
    private function _prettyJson(string $json): string
    {
        return Json::encode(Json::decode($json), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
