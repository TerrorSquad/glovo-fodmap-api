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
                            {--name-hashes= : Name hashes to classify (comma-separated for multiple)}
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

        if ($nameHashes = $this->option('name-hashes')) {
            $ids = array_map('trim', explode(',', $nameHashes));

            return $query->whereIn('name_hash', $ids)->get();
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
            $batchSize = 50; // Better default for Gemini 2.5 Flash Lite
        }

        // Limit batch size for optimal performance with Gemini 2.5 Flash Lite
        if ($batchSize > 100) {
            $this->warn('Batch size limited to 100 for optimal Gemini API performance');
            $batchSize = 100;
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
                // Add delay between batches to respect API rate limits (1 request per 2 seconds)
                if ($chunkIndex > 0) {
                    $this->comment('⏳ Waiting 2 seconds to respect API rate limits...');
                    sleep(2);
                }

                $results = $classifier->classifyBatch($batch->values()->all());

                foreach ($batch->values() as $index => $product) {
                    $originalStatus = $product->status;
                    $result         = $results[$product->name_hash] ?? [
                        'status'      => 'UNKNOWN',
                        'is_food'     => null,
                        'explanation' => 'No classification result received',
                    ];

                    $product->update([
                        'status'       => $result['status'],
                        'is_food'      => $result['is_food'],
                        'explanation'  => $result['explanation'],
                        'processed_at' => now(),
                    ]);
                    ++$classified;

                    if ($this->option('verbose')) {
                        $this->newLine();
                        $this->line('Product: ' . $product->name);
                        $this->line('Name Hash: ' . $product->name_hash);
                        $this->line('Category: ' . $product->category);
                        $this->line(sprintf('Status: %s → %s', $originalStatus, $result['status']));
                        $this->line('Is Food: ' . ($result['is_food'] === null ? 'null' : ($result['is_food'] ? 'true' : 'false')));
                        $this->line('Explanation: ' . ($result['explanation'] ?? 'null'));
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
                // Add delay between individual calls to respect rate limits (1 request per 2 seconds)
                if ($index > 0) {
                    sleep(2);
                }

                $originalStatus = $product->status;
                $result         = $classifier->classify($product);

                $product->update([
                    'status'       => $result['status'],
                    'is_food'      => $result['is_food'],
                    'explanation'  => $result['explanation'],
                    'processed_at' => now(),
                ]);
                ++$classified;

                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->line('Product: ' . $product->name);
                    $this->line('Name Hash: ' . $product->name_hash);
                    $this->line('Category: ' . $product->category);
                    $this->line(sprintf('Status: %s → %s', $originalStatus, $result['status']));
                    $this->line('Is Food: ' . ($result['is_food'] === null ? 'null' : ($result['is_food'] ? 'true' : 'false')));
                    $this->line('Explanation: ' . ($result['explanation'] ?? 'null'));
                    $this->line('---');
                }
            } catch (\Exception $e) {
                ++$errors;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->error(sprintf('Failed to classify product %s: %s', $product->name_hash, $e->getMessage()));
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
