<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\FodmapClassifierInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ClassifyProductsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<array{external_id: string, name: string, category: string}> $productsData
     */
    public function __construct(
        public readonly array $productsData
    ) {}

    public function handle(FodmapClassifierInterface $classifier): void
    {
        if ($this->productsData === []) {
            return;
        }

        Log::info('Starting background classification', [
            'product_count' => count($this->productsData),
        ]);

        try {
            // Prepare products for batch classification
            $productsForClassification = [];
            foreach ($this->productsData as $data) {
                $productsForClassification[] = new Product([
                    'external_id' => $data['external_id'],
                    'name'        => $data['name'],
                    'category'    => $data['category'],
                ]);
            }

            // Use batch classification for performance
            $classificationResults = $classifier->classifyBatch($productsForClassification);

            // Prepare data for bulk insert
            $productsToInsert = [];
            foreach ($this->productsData as $index => $data) {
                $productsToInsert[] = [
                    'external_id'  => $data['external_id'],
                    'name'         => $data['name'],
                    'category'     => $data['category'],
                    'status'       => $classificationResults[$index] ?? 'UNKNOWN',
                    'processed_at' => now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }

            // Insert all products at once
            Product::insert($productsToInsert);

            Log::info('Background classification completed successfully', [
                'product_count' => count($this->productsData),
                'classified'    => count($classificationResults),
            ]);
        } catch (\Exception $exception) {
            Log::error('Background classification failed', [
                'product_count' => count($this->productsData),
                'error'         => $exception->getMessage(),
                'trace'         => $exception->getTraceAsString(),
            ]);

            // Insert products with UNKNOWN status as fallback
            $fallbackProducts = [];
            foreach ($this->productsData as $data) {
                $fallbackProducts[] = [
                    'external_id'  => $data['external_id'],
                    'name'         => $data['name'],
                    'category'     => $data['category'],
                    'status'       => 'UNKNOWN',
                    'processed_at' => now(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }

            Product::insert($fallbackProducts);

            throw $exception; // Re-throw for queue retry mechanism
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60]; // Retry after 10s, then 30s, then 60s
    }

    /**
     * Determine the maximum number of attempts.
     */
    public function tries(): int
    {
        return 3;
    }
}
