<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\FodmapClassifierInterface;
use App\Services\FodmapClassifierService;
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
        // Bind the classifier interface to implementation
        $this->app->bind(FodmapClassifierInterface::class, function ($app): FodmapClassifierService|GeminiFodmapClassifierService {
            $useGemini = config('app.use_gemini_classifier', false);

            return $useGemini
                ? new GeminiFodmapClassifierService()
                : new FodmapClassifierService();
        });

        // Maintain backward compatibility
        $this->app->bind(FodmapClassifierService::class, fn ($app) => $app->make(FodmapClassifierInterface::class));
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
