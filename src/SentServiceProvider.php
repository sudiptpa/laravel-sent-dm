<?php

declare(strict_types=1);

namespace Sujip\SentDm;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rule;
use Sujip\SentDm\Channels\SentChannel;
use Sujip\SentDm\Commands\HealthCommand;
use Sujip\SentDm\Commands\InstallCommand;
use Sujip\SentDm\Commands\LookupCommand;
use Sujip\SentDm\Commands\SetupWebhookCommand;
use Sujip\SentDm\Commands\TemplatesCommand;
use Sujip\SentDm\Commands\TestSendCommand;
use Sujip\SentDm\Rules\SentMobileNumber;
use Sujip\SentDm\Webhooks\SentWebhookController;
use Sujip\SentDm\Webhooks\VerifySignature;

class SentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sent.php', 'sent');

        $this->app->singleton(SentManager::class, function (Application $app): SentManager {
            return new SentManager($app);
        });

        $this->app->alias(SentManager::class, 'sent');

        $this->app->bind(SentChannel::class, function (Application $app): SentChannel {
            return new SentChannel($app->make(SentManager::class)->connection());
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sent.php' => config_path('sent.php'),
            ], 'laravel-sent-config');

            $this->commands([
                InstallCommand::class,
                HealthCommand::class,
                TestSendCommand::class,
                TemplatesCommand::class,
                LookupCommand::class,
                SetupWebhookCommand::class,
            ]);
        }

        Rule::macro('sentMobileNumber', function (
            ?string $connection = null,
            bool $requireMobile = false,
        ): SentMobileNumber {
            return new SentMobileNumber($connection, $requireMobile);
        });

        if ((bool) config('sent.webhook.enabled')) {
            $this->registerWebhookRoute();
        }
    }

    private function registerWebhookRoute(): void
    {
        $router = $this->app->make('router');

        $path = config('sent.webhook.path');

        $router->post(
            is_string($path) ? $path : 'sent/webhook',
            SentWebhookController::class,
        )->middleware(VerifySignature::class)->name('sent.webhook');
    }
}
