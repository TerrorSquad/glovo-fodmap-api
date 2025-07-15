<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

interface FodmapClassifierInterface
{
    public function classify(Product $product): string;

    public function classifyBatch(array $products): array;
}
