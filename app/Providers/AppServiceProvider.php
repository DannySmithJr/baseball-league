<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\OotpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OotpService::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        DB::enableQueryLog();

        View::composer('*', function ($view) {
            $view->with('settings', Setting::instance());
            $view->with('ootp', app(OotpService::class));
        });
    }
}
