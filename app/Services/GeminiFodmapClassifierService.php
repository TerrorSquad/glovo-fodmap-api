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
        if ($products === []) {
            return [];
        }

        // For single product, use individual classification
        if (count($products) === 1) {
            return [$this->classify($products[0])];
        }

        try {
            $prompt = $this->buildBatchPrompt($products);

            $geminiClient = \Gemini::client(config('gemini.api_key'));

            $result = $geminiClient->generativeModel('gemini-2.0-flash-exp')->generateContent($prompt);

            $response = trim($result->text());

            return $this->parseBatchResponse($response, $products);
        } catch (\Exception $exception) {
            Log::error('Gemini batch classification failed', [
                'product_count' => count($products),
                'error'         => $exception->getMessage(),
            ]);

            // Fallback to individual classification
            $results = [];
            foreach ($products as $product) {
                $results[] = $this->classify($product);
            }

            return $results;
        }
    }

    private function buildPrompt(Product $product): string
    {
        return <<<PROMPT
            You are a FODMAP classification expert. Classify the following product based on FODMAP content.

            Product Name: {$product->name}
            Category: {$product->category}

            Classification Rules:
            - LOW: Food products that are generally safe for people with IBS (less than threshold amounts of FODMAPs)
            - HIGH: Food products that contain significant amounts of FODMAPs (fructans, lactose, fructose, polyols, etc.)
            - NA: Non-food products (cosmetics, cleaning products, toiletries, household items, etc.)
            - UNKNOWN: Food products where you cannot determine the FODMAP level with confidence

            Common HIGH FODMAP foods: wheat products, onions, garlic, beans, milk products, apples, pears, stone fruits, etc.
            Common LOW FODMAP foods: rice, potatoes, carrots, spinach, chicken, fish, lactose-free dairy, oranges, strawberries, etc.
            Non-food items: shampoo, detergent, toothpaste, cosmetics, cleaning supplies, etc.

            Important: If this is clearly not a food product, classify it as "NA" regardless of FODMAP content.

            Respond with only one word: "low", "high", "na", or "unknown"
            PROMPT;
    }

    private function buildBatchPrompt(array $products): string
    {
        $productList = '';
        foreach ($products as $index => $product) {
            $productIndex = $index + 1;
            $productList .= sprintf('%d. Name: %s%s', $productIndex, $product->name, PHP_EOL);
            $productList .= sprintf('   Category: %s%s', $product->category, PHP_EOL);
            $productList .= "   External ID: {$product->external_id}\n\n";
        }

        return <<<PROMPT
            You are a FODMAP classification expert. Classify the following products based on FODMAP content.

            Products to classify:
            {$productList}

            Classification Rules:
            - LOW: Food products that are generally safe for people with IBS (less than threshold amounts of FODMAPs)
            - HIGH: Food products that contain significant amounts of FODMAPs (fructans, lactose, fructose, polyols, etc.)
            - NA: Non-food products (cosmetics, cleaning products, toiletries, household items, personal care, etc.)
            - UNKNOWN: Food products where you cannot determine the FODMAP level with confidence

            Common HIGH FODMAP foods: wheat products, onions, garlic, beans, milk products, apples, pears, stone fruits, etc.
            Common LOW FODMAP foods: rice, potatoes, carrots, spinach, chicken, fish, lactose-free dairy, oranges, strawberries, etc.
            
            Non-food items include: shampoo, detergent, toothpaste, cosmetics, cleaning supplies, toilet paper, tissues, soap, household cleaners, personal care items, etc.

            CRITICAL: Carefully examine each product. If ANY product is clearly not a food product (cleaning, household, personal care, cosmetics, etc.), it MUST be classified as "NA" regardless of any other considerations.

            For each product:
            1. First determine: Is this a food product or non-food product?
            2. If non-food: classify as "na"
            3. If food: evaluate FODMAP content and classify as "low", "high", or "unknown"

            Respond with classifications in the exact format:
            1: [classification]
            2: [classification]
            3: [classification]
            ...

            Where [classification] is one word: "low", "high", "na", or "unknown"

            Example response:
            1: low
            2: high
            3: na
            PROMPT;
    }

    private function parseBatchResponse(string $response, array $products): array
    {
        $results = [];
        $lines   = array_filter(explode("\n", $response));

        foreach ($lines as $line) {
            if (preg_match('/^(\d+):\s*(\w+)/', trim($line), $matches)) {
                $index          = (int) $matches[1] - 1; // Convert to 0-based index
                $classification = $this->normalizeClassification($matches[2]);

                if (isset($products[$index])) {
                    $results[$index] = $classification;
                }
            }
        }
        // Fill in any missing results with UNKNOWN
        $counter = count($products);

        // Fill in any missing results with UNKNOWN
        for ($i = 0; $i < $counter; ++$i) {
            if (! isset($results[$i])) {
                $results[$i] = 'UNKNOWN';
                Log::warning('Missing classification result for product index ' . $i, [
                    'product_name' => $products[$i]->name ?? 'Unknown',
                    'response'     => $response,
                ]);
            }
        }

        // Ensure results are in the correct order
        ksort($results);

        return array_values($results);
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

        if (str_contains($normalized, 'na')) {
            return 'NA';
        }

        return 'UNKNOWN';
    }
}
