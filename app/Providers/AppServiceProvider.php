<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\FodmapClassifierInterface;
use App\Services\GeminiFodmapClassifierService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind to direct Gemini classifier - database already prevents redundant calls
        $this->app->bind(FodmapClassifierInterface::class, GeminiFodmapClassifierService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
