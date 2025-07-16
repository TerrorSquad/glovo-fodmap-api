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
            // Fetch actual Product models from database for classification
            $externalIds = array_column($this->productsData, 'external_id');
            $products    = Product::whereIn('external_id', $externalIds)->get();

            if ($products->isEmpty()) {
                Log::warning('No products found for classification', [
                    'external_ids' => $externalIds,
                ]);

                return;
            }

            // Use batch classification for performance
            $classificationResults = $classifier->classifyBatch($products->all());

            // Update products with classification results
            foreach ($products as $index => $product) {
                $originalStatus = $product->status;
                $newStatus      = $classificationResults[$index] ?? 'UNKNOWN';

                $product->update([
                    'status'       => $newStatus,
                    'processed_at' => now(),
                ]);

                Log::debug('Product classified', [
                    'external_id' => $product->external_id,
                    'name'        => $product->name,
                    'status'      => $originalStatus . ' → ' . $newStatus,
                ]);
            }

            Log::info('Background classification completed successfully', [
                'product_count' => $products->count(),
                'classified'    => count($classificationResults),
            ]);
        } catch (\Exception $exception) {
            Log::error('Background classification failed', [
                'product_count' => count($this->productsData),
                'error'         => $exception->getMessage(),
                'trace'         => $exception->getTraceAsString(),
            ]);

            // Update products with UNKNOWN status as fallback
            $externalIds = array_column($this->productsData, 'external_id');
            Product::whereIn('external_id', $externalIds)->update([
                'status'       => 'UNKNOWN',
                'processed_at' => now(),
                'updated_at'   => now(),
            ]);

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
