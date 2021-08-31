<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license MIT
 */

namespace craft\webhooks\assets\activity;

use craft\web\assets\cp\CpAsset;
use yii\web\AssetBundle;

/**
 * Webhooks index asset bundle
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 1.0.0
 */
class ActivityAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $depends = [
        CpAsset::class,
    ];

    public $css = [
        'css/activity.css',
    ];

    public $js = [
        'js/Activity.js',
    ];
}
