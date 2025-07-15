<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

class GeminiFodmapClassifierService implements FodmapClassifierInterface
{
    public function classify(Product $product): string
    {
        try {
            $prompt = $this->buildPrompt($product);

            $geminiClient = \Gemini::client(config('gemini.api_key'));

            $result = $geminiClient->generativeModel('gemini-2.0-flash-exp')->generateContent($prompt);

            $classification = trim($result->text());

            return $this->normalizeClassification($classification);
        } catch (\Exception $exception) {
            Log::error('Gemini classification failed', [
                'product_name' => $product->name,
                'error'        => $exception->getMessage(),
            ]);

            // Fallback to safe classification
            return 'UNKNOWN';
        }
    }

    public function classifyBatch(array $products): array
    {
        $results = [];

        foreach ($products as $product) {
            $results[] = $this->classify($product);
        }

        return $results;
    }

    private function buildPrompt(Product $product): string
    {
        return <<<PROMPT
            You are a FODMAP classification expert. Classify the following food product as either "low", "high", or "unknown" based on FODMAP content.

            Product Name: {$product->name}
            Category: {$product->category}

            FODMAP Classification Rules:
            - LOW: Foods that are generally safe for people with IBS (less than threshold amounts of FODMAPs)
            - HIGH: Foods that contain significant amounts of FODMAPs (fructans, lactose, fructose, polyols, etc.)
            - UNKNOWN: When you cannot determine the FODMAP level with confidence

            Common HIGH FODMAP foods include: wheat products, onions, garlic, beans, milk products, apples, pears, stone fruits, etc.
            Common LOW FODMAP foods include: rice, potatoes, carrots, spinach, chicken, fish, lactose-free dairy, oranges, strawberries, etc.

            Respond with only one word: "low", "high", or "unknown"
            PROMPT;
    }

    private function normalizeClassification(string $classification): string
    {
        $normalized = strtolower(trim($classification));

        // Handle various response formats and return uppercase to match existing data
        if (str_contains($normalized, 'low')) {
            return 'LOW';
        }

        if (str_contains($normalized, 'high')) {
            return 'HIGH';
        }

        return 'UNKNOWN';
    }
}
