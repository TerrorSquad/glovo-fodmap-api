<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CachedFodmapClassifierService implements FodmapClassifierInterface
{
    public function __construct(
        private readonly GeminiFodmapClassifierService $classifierService
    ) {}

    /**
     * Classify a single product with caching.
     */
    public function classify(object $product): string
    {
        $cacheKey = $this->generateCacheKey($product->name, $product->category ?? '');

        // Check cache first
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            Log::info('Using cached FODMAP classification', [
                'product' => $product->name,
                'result'  => $cachedResult,
            ]);

            return $cachedResult;
        }

        // Classify and cache result
        $result = $this->classifierService->classify($product);

        if ($result !== 'UNKNOWN') {
            // Cache successful classifications for 30 days
            Cache::put($cacheKey, $result, now()->addDays(30));

            Log::info('Cached new FODMAP classification', [
                'product' => $product->name,
                'result'  => $result,
            ]);
        }

        return $result;
    }

    /**
     * Classify multiple products with intelligent caching.
     */
    public function classifyBatch(array $products): array
    {
        $results          = [];
        $uncachedProducts = [];

        // Check cache for all products first
        foreach ($products as $product) {
            $cacheKey     = $this->generateCacheKey($product->name, $product->category ?? '');
            $cachedResult = Cache::get($cacheKey);

            if ($cachedResult !== null) {
                $results[$product->external_id] = $cachedResult;
            } else {
                $uncachedProducts[] = $product;
            }
        }

        Log::info('Batch classification cache stats', [
            'total_products'       => count($products),
            'cached_hits'          => count($results),
            'needs_classification' => count($uncachedProducts),
        ]);

        // Only classify uncached products
        if ($uncachedProducts !== []) {
            $newResults = $this->classifierService->classifyBatch($uncachedProducts);

            // Cache new results and merge with cached ones
            foreach ($newResults as $externalId => $result) {
                $product = collect($uncachedProducts)->firstWhere('external_id', $externalId);
                if ($product && $result !== 'UNKNOWN') {
                    $cacheKey = $this->generateCacheKey($product->name, $product->category ?? '');
                    Cache::put($cacheKey, $result, now()->addDays(30));
                }

                $results[$externalId] = $result;
            }
        }

        return $results;
    }

    /**
     * Clear classification cache.
     */
    public function clearCache(?string $pattern = null): void
    {
        if ($pattern) {
            // For pattern-based clearing, we'll need to iterate through possible keys
            // This is a simple implementation; for production, consider using cache tags
            Log::info('Pattern-based cache clearing not implemented, clearing all cache', [
                'requested_pattern' => $pattern,
            ]);
        }

        // Clear all classification cache
        Cache::flush();

        Log::info('Cleared FODMAP classification cache', [
            'pattern' => $pattern ?? 'all',
        ]);
    }

    /**
     * Generate a consistent cache key for a product.
     */
    private function generateCacheKey(string $name, string $category): string
    {
        $normalizedName     = strtolower(trim($name));
        $normalizedCategory = strtolower(trim($category));

        return 'fodmap_classification:' . sha1($normalizedName . '|' . $normalizedCategory);
    }
}
