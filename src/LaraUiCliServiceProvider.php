<?php

namespace Amsiam\LaraUiCli;

use Amsiam\LaraUiCli\Commands\InitCommand;
use Amsiam\LaraUiCli\Commands\AddCommand;
use Amsiam\LaraUiCli\Commands\ListCommand;
use Amsiam\LaraUiCli\Commands\DiffCommand;
use Illuminate\Support\ServiceProvider;

class LaraUiCliServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComponentRegistry::class, function () {
            return new ComponentRegistry();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InitCommand::class,
                AddCommand::class,
                ListCommand::class,
                DiffCommand::class,
            ]);
        }
    }
}
