<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ClassifyProductsJob;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPendingClassifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fodmap:process-pending';

    /**
     * The console command description.
     */
    protected $description = 'Process pending FODMAP product classifications';

    public function handle(): int
    {
        $pendingCount = Product::where('status', 'PENDING')
            ->whereNull('processed_at')
            ->count()
        ;

        if ($pendingCount === 0) {
            $this->info('No pending products to classify.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %s pending products. Starting classification...', $pendingCount));

        // Dispatch the classification job
        ClassifyProductsJob::dispatch();

        Log::info('Scheduled classification job dispatched', [
            'pending_products' => $pendingCount,
        ]);

        $this->info('Classification job dispatched successfully.');

        return self::SUCCESS;
    }
}
