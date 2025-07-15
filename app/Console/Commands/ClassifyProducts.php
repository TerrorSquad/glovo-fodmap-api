<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\FodmapClassifierInterface;
use App\Services\FodmapClassifierService;
use App\Services\GeminiFodmapClassifierService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ClassifyProducts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fodmap:classify
                            {--external-id= : External ID of a single product to classify}
                            {--external-ids= : Comma-separated list of external IDs to classify}
                            {--all : Classify all products in the database}
                            {--force : Re-classify products even if they already have a status}
                            {--ai : Use AI classifier (overrides config setting)}
                            {--rules : Use rule-based classifier (overrides config setting)}
                            {--batch-size=10 : Number of products to classify in each batch (AI only)}
                            {--no-batch : Disable batch processing and classify one by one}';

    /**
     * The console command description.
     */
    protected $description = 'Classify products using FODMAP classification service';

    public function handle(FodmapClassifierInterface $classifier): int
    {
        // Override classifier if specific option is provided
        $classifier = $this->getClassifierInstance();

        $products = $this->getProductsToClassify();

        if ($products->isEmpty()) {
            $this->error('No products found to classify.');

            return self::FAILURE;
        }

        $this->info(sprintf('Found %d product(s) to classify.', $products->count()));

        $batchSize      = $this->getBatchSize($classifier);
        $shouldUseBatch = $this->shouldUseBatchProcessing($classifier, $products->count());

        if ($shouldUseBatch && $batchSize > 1) {
            $this->info(sprintf('Using batch processing with batch size: %d', $batchSize));

            return $this->classifyInBatches($classifier, $products, $batchSize);
        }

        $this->info('Using individual product classification');

        return $this->classifyIndividually($classifier, $products);
    }

    private function getProductsToClassify(): Collection
    {
        $query = Product::query();

        if ($externalId = $this->option('external-id')) {
            return $query->where('external_id', $externalId)->get();
        }

        if ($externalIds = $this->option('external-ids')) {
            $ids = array_map('trim', explode(',', $externalIds));

            return $query->whereIn('external_id', $ids)->get();
        }

        if ($this->option('all')) {
            if (! $this->option('force')) {
                // Only classify products without status or with 'unknown' or 'PENDING' status
                $query->where(function ($q): void {
                    $q->whereNull('status')
                        ->orWhere('status', 'unknown')
                        ->orWhere('status', 'UNKNOWN')
                        ->orWhere('status', 'PENDING')
                        ->orWhere('status', '')
                    ;
                });
            }

            return $query->get();
        }

        // If no specific option is provided, ask for confirmation
        if (! $this->confirm('No specific products selected. Do you want to classify all unclassified products?')) {
            return collect();
        }

        return $query->where(function ($q): void {
            $q->whereNull('status')
                ->orWhere('status', 'unknown')
                ->orWhere('status', 'UNKNOWN')
                ->orWhere('status', 'PENDING')
                ->orWhere('status', '')
            ;
        })->get();
    }

    private function getClassifierInstance(): FodmapClassifierInterface
    {
        // Check for override options
        if ($this->option('ai')) {
            return app(GeminiFodmapClassifierService::class);
        }

        if ($this->option('rules')) {
            return app(FodmapClassifierService::class);
        }

        // Use configured classifier
        return app(FodmapClassifierInterface::class);
    }

    private function getBatchSize(FodmapClassifierInterface $classifier): int
    {
        $batchSize = (int) $this->option('batch-size');

        // Validate batch size
        if ($batchSize < 1) {
            $batchSize = 10;
        }

        // Limit batch size for safety
        if ($batchSize > 50) {
            $this->warn('Batch size limited to 50 for API safety');
            $batchSize = 50;
        }

        return $batchSize;
    }

    private function shouldUseBatchProcessing(FodmapClassifierInterface $classifier, int $productCount): bool
    {
        // Don't use batch for rule-based classifier
        if (! $classifier instanceof GeminiFodmapClassifierService) {
            return false;
        }

        // Respect the no-batch option
        if ($this->option('no-batch')) {
            return false;
        }

        // Only use batch processing if we have more than 1 product
        return $productCount > 1;
    }

    private function classifyInBatches(FodmapClassifierInterface $classifier, Collection $products, int $batchSize): int
    {
        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $classified = 0;
        $errors     = 0;
        $chunks     = $products->chunk($batchSize);

        foreach ($chunks as $batch) {
            try {
                $results = $classifier->classifyBatch($batch->values()->all());

                foreach ($batch as $index => $product) {
                    $originalStatus = $product->status;
                    $newStatus      = $results[$index] ?? 'UNKNOWN';

                    $product->update([
                        'status' => $newStatus,
                    ]);
                    ++$classified;

                    if ($this->option('verbose')) {
                        $this->newLine();
                        $this->line('Product: ' . $product->name);
                        $this->line('External ID: ' . $product->external_id);
                        $this->line('Category: ' . $product->category);
                        $this->line(sprintf('Status: %s → %s', $originalStatus, $newStatus));
                        $this->line('---');
                    }

                    $bar->advance();
                }
            } catch (\Exception $e) {
                $errors += $batch->count();

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error(sprintf('Failed to classify batch of %d products: %s', $batch->count(), $e->getMessage()));
                }

                $bar->advance($batch->count());
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Batch classification completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Products', $products->count()],
                ['Successfully Classified', $classified],
                ['Errors', $errors],
                ['Batch Size Used', $batchSize],
                ['Number of Batches', $chunks->count()],
            ]
        );

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function classifyIndividually(FodmapClassifierInterface $classifier, Collection $products): int
    {
        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $classified = 0;
        $errors     = 0;

        foreach ($products as $product) {
            try {
                $originalStatus = $product->status;
                $newStatus      = $classifier->classify($product);

                $product->update([
                    'status' => $newStatus,
                ]);
                ++$classified;

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->line('Product: ' . $product->name);
                    $this->line('External ID: ' . $product->external_id);
                    $this->line('Category: ' . $product->category);
                    $this->line(sprintf('Status: %s → %s', $originalStatus, $newStatus));
                    $this->line('---');
                }
            } catch (\Exception $e) {
                ++$errors;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error(sprintf('Failed to classify product %s: %s', $product->external_id, $e->getMessage()));
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Individual classification completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Products', $products->count()],
                ['Successfully Classified', $classified],
                ['Errors', $errors],
            ]
        );

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
