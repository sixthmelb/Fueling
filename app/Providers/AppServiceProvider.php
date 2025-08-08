<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            if (class_exists(\App\Models\FuelTransaction::class)) {
                \App\Models\FuelTransaction::observe(\App\Observers\FuelTransactionObserver::class);
            }
            
            if (class_exists(\App\Models\FuelTransfer::class)) {
                \App\Models\FuelTransfer::observe(\App\Observers\FuelTransferObserver::class);
            }
        });
    }
}
