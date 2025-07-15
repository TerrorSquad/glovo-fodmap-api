<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
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
                            {--reprocess : Re-classify products that have already been processed}
                            {--batch-size=10 : Number of products to classify in each batch}
                            {--no-batch : Disable batch processing and classify one by one}';

    /**
     * The console command description.
     */
    protected $description = 'Classify products using FODMAP classification service';

    public function handle(): int
    {
        $classifier = app(GeminiFodmapClassifierService::class);

        $products = $this->getProductsToClassify();

        if ($products->isEmpty()) {
            $this->error('No products found to classify.');

            return self::FAILURE;
        }

        $this->info(sprintf('Found %d product(s) to classify.', $products->count()));

        $batchSize      = $this->getBatchSize();
        $shouldUseBatch = $this->shouldUseBatchProcessing($products->count());

        if ($shouldUseBatch && $batchSize > 1) {
            $this->info(sprintf('Using batch processing with batch size: %d', $batchSize));
            $this->comment('⚠️  Note: Gemini API Tier 1 has high rate limits. Large batches will process quickly.');

            return $this->classifyInBatches($classifier, $products, $batchSize);
        }

        $this->info('Using individual product classification');
        $this->comment('⚠️  Note: Individual classification will be slower than batch processing.');

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
                // Only classify products that haven't been processed OR have specific statuses that need reprocessing
                $query->where(function ($q): void {
                    if (! $this->option('reprocess')) {
                        $q->whereNull('processed_at'); // Never been processed
                    }

                    $q->where(function ($subQ): void {
                        $subQ->whereNull('status')
                            ->orWhere('status', 'unknown')
                            ->orWhere('status', 'UNKNOWN')
                            ->orWhere('status', 'PENDING')
                            ->orWhere('status', '')
                        ;
                    });
                });
            }

            return $query->get();
        }

        // If no specific option is provided, ask for confirmation
        if (! $this->confirm('No specific products selected. Do you want to classify all unclassified products?')) {
            return collect();
        }

        return $query->where(function ($q): void {
            if (! $this->option('reprocess')) {
                $q->whereNull('processed_at'); // Never been processed
            }

            $q->where(function ($subQ): void {
                $subQ->whereNull('status')
                    ->orWhere('status', 'unknown')
                    ->orWhere('status', 'UNKNOWN')
                    ->orWhere('status', 'PENDING')
                    ->orWhere('status', '')
                ;
            });
        })->get();
    }

    private function getBatchSize(): int
    {
        $batchSize = (int) $this->option('batch-size');

        // Validate batch size
        if ($batchSize < 1) {
            $batchSize = 10;
        }

        // Limit batch size for optimal performance with Gemini 2.0 Flash (Tier 1 has much higher rate limits)
        if ($batchSize > 50) {
            $this->warn('Batch size limited to 50 for optimal Gemini API performance');
            $batchSize = 50;
        }

        return $batchSize;
    }

    private function shouldUseBatchProcessing(int $productCount): bool
    {
        // Respect the no-batch option
        if ($this->option('no-batch')) {
            return false;
        }

        // Only use batch processing if we have more than 1 product
        return $productCount > 1;
    }

    private function classifyInBatches(GeminiFodmapClassifierService $classifier, Collection $products, int $batchSize): int
    {
        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $classified = 0;
        $errors     = 0;
        $chunks     = $products->chunk($batchSize);

        foreach ($chunks as $chunkIndex => $batch) {
            try {
                // Add delay between batches to respect API rate limits (conservative approach)
                if ($chunkIndex > 0) {
                    $this->comment('⏳ Waiting 5 seconds to respect API rate limits...');
                    sleep(5);
                }

                $results = $classifier->classifyBatch($batch->values()->all());

                foreach ($batch as $index => $product) {
                    $originalStatus = $product->status;
                    $newStatus      = $results[$index] ?? 'UNKNOWN';

                    $product->update([
                        'status'       => $newStatus,
                        'processed_at' => now(),
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

    private function classifyIndividually(GeminiFodmapClassifierService $classifier, Collection $products): int
    {
        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $classified = 0;
        $errors     = 0;

        foreach ($products as $index => $product) {
            try {
                // Add delay between individual calls to respect rate limits (conservative approach)
                if ($index > 0) {
                    sleep(4);
                }

                $originalStatus = $product->status;
                $newStatus      = $classifier->classify($product);

                $product->update([
                    'status'       => $newStatus,
                    'processed_at' => now(),
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
