<?php

namespace LokyHelpMe;

use Illuminate\Support\ServiceProvider;
use LokyHelpMe\Console\LokyHelpMeCommand;
use LokyHelpMe\Services\TableInspector;

class LokyHelpMeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TableInspector::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LokyHelpMeCommand::class,
            ]);
        }
    }
}
