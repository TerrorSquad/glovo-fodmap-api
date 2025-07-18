<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CachedFodmapClassifierService;
use App\Services\FodmapClassifierInterface;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind to Gemini classifier
        $this->app->bind(FodmapClassifierInterface::class, CachedFodmapClassifierService::class);
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
