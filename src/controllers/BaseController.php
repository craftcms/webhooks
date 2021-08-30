<?php

namespace craft\webhooks\controllers;

use craft\web\Controller;

/**
 * Base Webhooks Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0.0
 */
abstract class BaseController extends Controller
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
}
