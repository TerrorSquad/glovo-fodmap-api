<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);

            // Run classification job every 2 minutes with overlap protection
            $schedule->command('fodmap:process-pending')
                ->everyTwoMinutes()
                ->withoutOverlapping(5) // Prevent overlapping for 5 minutes
                ->runInBackground()
            ;
        });
    }
}
