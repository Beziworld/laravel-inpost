<?php

declare(strict_types=1);

namespace Beziworld\InPost;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the InPost client and the webhook route.
 */
final class InPostServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/inpost.php', 'inpost');

        $this->app->singleton(InPostConfig::class, static function (Application $app): InPostConfig {
            /** @var array<string, mixed> $config */
            $config = $app->make(Config::class)->get('inpost', []);

            return InPostConfig::fromArray($config);
        });

        $this->app->singleton(InPostClient::class, static fn(Application $app): InPostClient => new InPostClient(
            $app->make(HttpFactory::class),
            $app->make(InPostConfig::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/inpost.php' => $this->app->configPath('inpost.php')], 'inpost-config');
        }

        if ((bool) $this->app->make(Config::class)->get('inpost.webhooks.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/inpost.php');
        }
    }
}
