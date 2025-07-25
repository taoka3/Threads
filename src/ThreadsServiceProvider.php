<?php

namespace taoka3\Threads;

use Illuminate\Support\ServiceProvider;

class ThreadsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // マイグレーションの読み込み
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // configファイルのpublish設定
        $this->publishes([
            __DIR__ . '/config/threads.php' => config_path('threads.php'),
        ], 'threads-config');

        // マイグレーションファイルのpublish設定
        $this->publishes([
            __DIR__ . '/database/migrations/' => database_path('migrations'),
        ], 'threads-migrations');
    }
}
