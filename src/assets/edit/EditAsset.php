<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\webhooks\assets\edit;

use craft\web\assets\cp\CpAsset;
use yii\web\AssetBundle;

/**
 * Webhooks index asset bundle
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0.0
 */
class EditAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $depends = [
        CpAsset::class,
    ];

    public $js = [
        'js/EditWebhook.js',
    ];

    public $css = [
        'css/edit-webhook.css',
    ];
}
