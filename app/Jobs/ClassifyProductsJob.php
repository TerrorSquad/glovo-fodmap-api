<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Services\FodmapClassifierInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClassifyProductsJob implements ShouldQueue
{
    use Queueable;

    private const LAST_JOB_KEY = 'last_classification_job_time';

    private const MIN_JOB_INTERVAL = 2; // seconds between jobs

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

        // Respect rate limits by adding delay between jobs if needed
        $this->respectRateLimits();

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

            // Mark job completion for rate limiting
            $this->markJobCompleted();
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

            // Mark job completion even on failure for rate limiting
            $this->markJobCompleted();

            throw $exception; // Re-throw for queue retry mechanism
        }

        $this->markJobCompleted();
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

    /**
     * Ensure proper delays between jobs to respect API rate limits.
     */
    private function respectRateLimits(): void
    {
        $lastJobTime = Cache::get(self::LAST_JOB_KEY);

        if ($lastJobTime) {
            // Calculate time difference properly - negative means we're in the future somehow
            $timeSinceLastJob = now()->diffInSeconds($lastJobTime, false);

            // Only sleep if not enough time has passed and the time makes sense
            if ($timeSinceLastJob >= 0 && $timeSinceLastJob < self::MIN_JOB_INTERVAL) {
                $sleepTime = (int) ceil(self::MIN_JOB_INTERVAL - $timeSinceLastJob);
                Log::info('Delaying job execution to respect rate limits', [
                    'sleep_seconds'       => $sleepTime,
                    'time_since_last_job' => $timeSinceLastJob,
                    'last_job_time'       => $lastJobTime->toDateTimeString(),
                    'current_time'        => now()->toDateTimeString(),
                ]);
                sleep($sleepTime);
            } else {
                Log::debug('No delay needed for job execution', [
                    'time_since_last_job' => $timeSinceLastJob,
                    'min_interval'        => self::MIN_JOB_INTERVAL,
                    'last_job_time'       => $lastJobTime->toDateTimeString(),
                ]);
            }
        }

        // Update the last job execution time to NOW (at the END of processing)
    }

    /**
     * Mark job completion time for rate limiting.
     */
    private function markJobCompleted(): void
    {
        Cache::put(self::LAST_JOB_KEY, now(), 300); // Cache for 5 minutes
    }
}
