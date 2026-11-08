<?php

declare(strict_types=1);

namespace CraftCms\Webhooks\Tests;

use CraftCms\Cms\Plugin\Testing\PluginTestCase;
use CraftCms\Cms\User\Models\User as UserModel;
use CraftCms\UrlValidator\UrlValidator;
use CraftCms\Webhooks\Settings;
use CraftCms\Webhooks\Webhooks;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

abstract class TestCase extends PluginTestCase
{
    /** The URL webhooks send requests to. */
    public const string RECEIVER_URL = 'https://receiver.test/hook';

    /** The IP addresses that test hostnames resolve to, so URL validation doesn’t need DNS. */
    public const array HOSTS = [
        'receiver.test' => ['93.184.215.14'],
        'internal.test' => ['10.0.0.5'],
        'other-internal.test' => ['10.0.0.6'],
        'metadata.test' => ['169.254.169.254'],
    ];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Use Craft’s model factories, as core’s tests do
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'CraftCms\\Cms\\Database\\Factories\\'.class_basename($modelName).'Factory',
        );

        $this->app->when(Webhooks::class)
            ->needs(UrlValidator::class)
            ->give(fn () => new UrlValidator(
                fn (string $host) => self::HOSTS[$host] ?? [],
                ['allowedPrivateHosts' => $this->app->make(Settings::class)->allowedPrivateHosts],
            ));
        $this->app->forgetInstance(Webhooks::class);
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Testbench’s default user model can’t receive the notifications that control panel pages show
        $app['config']->set('auth.providers.users.model', UserModel::class);

        $app['config']->set('craft.webhooks', [
            'maxAttempts' => 3,
            'retryDelay' => 1,
            'allowedPrivateHosts' => ['internal.test'],
        ]);
    }
}
