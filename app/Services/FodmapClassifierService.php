<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class FodmapClassifierService
{
    private readonly array $lowFodmapData;

    private readonly array $highFodmapData;

    private readonly array $ignoreList;

    public function __construct()
    {
        $this->lowFodmapData  = config('fodmap.low');
        $this->highFodmapData = config('fodmap.high');
        $this->ignoreList     = config('fodmap.ignore', []);
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
        $normalized = Str::ascii(strtolower($text));

        $normalized = str_replace($this->ignoreList, '', $normalized);

        $normalized = preg_replace('/\b\d+g\b|\b\d+ml\b|\b\d+l\b|\b\d+kg\b|\b\d+\b/', '', $normalized);

        return trim((string) preg_replace('/\s+/', ' ', (string) $normalized));
    }

    private function hasMatch(string $text, array $fodmapData): bool
    {
        $keywords = array_merge(
            $fodmapData['keywords'],
            array_keys($fodmapData['synonyms'])
        );

        foreach ($keywords as $keyword) {
            if (preg_match('/\b' . $this->normalize($keyword) . '\b/', $text)) {
                return true;
            }
        }

        return false;
    }
}
