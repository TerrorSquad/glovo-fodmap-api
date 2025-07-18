<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

interface FodmapClassifierInterface
{
    /**
     * Classify a single product and return detailed classification data.
     *
     * @return array{status: string, is_food: null|bool, explanation: null|string}
     */
    public function classify(Product $product): array;

    /**
     * Classify multiple products in batch.
     *
     * @param array<Product> $products
     *
     * @return array<string, array{status: string, is_food: null|bool, explanation: null|string}>
     */
    public function classifyBatch(array $products): array;
}
