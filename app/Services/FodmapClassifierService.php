<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class FodmapClassifierService
{
    private readonly array $lowFodmapData;

    private readonly array $highFodmapData;

    public function __construct()
    {
        $this->lowFodmapData  = config('fodmap.low');
        $this->highFodmapData = config('fodmap.high');
    }

    public function classify(Product $product): string
    {
        $textToSearch = $this->normalize($product->name . ' ' . $product->category);

        if ($this->hasMatch($textToSearch, $this->highFodmapData)) {
            return 'HIGH';
        }

        if ($this->hasMatch($textToSearch, $this->lowFodmapData)) {
            return 'LOW';
        }

        return 'UNKNOWN';
    }

    private function normalize(string $text): string
    {
        return Str::slug($text, ' ');
    }

    private function hasMatch(string $text, array $fodmapData): bool
    {
        foreach ($fodmapData['keywords'] as $keyword) {
            if (str_contains($text, $this->normalize($keyword))) {
                return true;
            }
        }

        foreach ($fodmapData['synonyms'] as $synonym => $original) {
            if (str_contains($text, $this->normalize($synonym))) {
                return true;
            }
        }

        return false;
    }
}
