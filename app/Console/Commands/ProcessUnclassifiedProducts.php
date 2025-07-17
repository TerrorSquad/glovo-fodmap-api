<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ClassifyProductsJob;
use App\Models\Product;
use App\Services\FodmapClassifierInterface;
use Illuminate\Console\Command;

class ProcessUnclassifiedProducts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'products:process-unclassified 
                            {--immediate : Process immediately instead of queueing}
                            {--limit=50 : Maximum number of products to process}';

    /**
     * The console command description.
     */
    protected $description = 'Process unclassified products through FODMAP classification';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $unclassifiedCount = Product::where('status', 'PENDING')
            ->whereNull('processed_at')
            ->count()
        ;

        if ($unclassifiedCount === 0) {
            $this->info('No unclassified products found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %s unclassified products.', $unclassifiedCount));

        if ($this->option('immediate')) {
            $this->info('Processing products immediately...');

            $limit    = (int) $this->option('limit');
            $products = Product::where('status', 'PENDING')
                ->whereNull('processed_at')
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get()
            ;

            if ($products->isEmpty()) {
                $this->info('No products to process.');

                return self::SUCCESS;
            }

            $this->info(sprintf('Processing %s products...', $products->count()));

            // Create and handle the job immediately
            $job = new ClassifyProductsJob();
            $job->handle(app(FodmapClassifierInterface::class));

            $this->info('Products processed successfully.');
        } else {
            $this->info('Dispatching classification job to queue...');
            ClassifyProductsJob::dispatch();
            $this->info('Job dispatched successfully. Products will be processed in the background.');
        }

        return self::SUCCESS;
    }
}
