<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiFodmapClassifierService implements FodmapClassifierInterface
{
    private const RATE_LIMIT_KEY = 'gemini_api_calls';

    private const MAX_CALLS_PER_MINUTE = 15;

    // Gemini 2.0 Flash free tier limit
    private const RATE_LIMIT_WINDOW = 60; // seconds

    public function classify(Product $product): string
    {
        // Check rate limit before making API call
        if (! $this->canMakeApiCall()) {
            Log::warning('Gemini API rate limit reached, falling back to UNKNOWN', [
                'product_name'  => $product->name,
                'current_calls' => $this->getCurrentCallCount(),
            ]);

            return $product->status;
        }

        try {
            $this->incrementCallCount();

            $prompt = $this->buildPrompt($product);

            $geminiClient = \Gemini::client(config('gemini.api_key'));

            $result = $geminiClient->generativeModel('gemini-2.0-flash-exp')->generateContent($prompt);

            $classification = trim($result->text());

            // Debug logging for Serbian products
            Log::info('Gemini classification debug', [
                'product_name'   => $product->name,
                'category'       => $product->category,
                'raw_response'   => $classification,
                'normalized'     => $this->normalizeClassification($classification),
                'api_calls_used' => $this->getCurrentCallCount(),
            ]);

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

        // Check rate limit before making API call
        if (! $this->canMakeApiCall()) {
            Log::warning('Gemini API rate limit reached for batch, falling back to individual classification', [
                'product_count' => count($products),
                'current_calls' => $this->getCurrentCallCount(),
            ]);

            // Fallback to individual classification (which also respects rate limits)
            $results = [];
            foreach ($products as $product) {
                $results[] = $this->classify($product);
            }

            return $results;
        }

        try {
            $this->incrementCallCount();

            $prompt = $this->buildBatchPrompt($products);

            $geminiClient = \Gemini::client(config('gemini.api_key'));

            $result = $geminiClient->generativeModel('gemini-2.0-flash-exp')->generateContent($prompt);

            $response = trim($result->text());

            Log::info('Gemini batch classification debug', [
                'product_count'  => count($products),
                'api_calls_used' => $this->getCurrentCallCount(),
                'raw_response'   => substr($response, 0, 200) . '...', // Log first 200 chars
            ]);

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

            CRITICAL: Product names are in Serbian/Bosnian/Croatian/Montenegrin language. You MUST translate and understand them first.

            Translation dictionary:
            - "kokos" = coconut (LOW FODMAP in normal portions)
            - "komad/komadići" = piece/pieces
            - "očišćeni" = cleaned/peeled
            - "instant kafa" = instant coffee (LOW FODMAP)
            - "hleb/hljeb/kruh" = bread (HIGH if wheat, LOW if gluten-free)
            - "mlijeko/mleko" = milk (HIGH FODMAP due to lactose)
            - "jogurt" = yogurt (HIGH FODMAP due to lactose)
            - "jabuka" = apple (HIGH FODMAP due to fructose)
            - "kruška" = pear (HIGH FODMAP due to fructose)
            - "luk" = onion (HIGH FODMAP due to fructans)
            - "beli luk" = garlic (HIGH FODMAP due to fructans)
            - "pasulj" = beans (HIGH FODMAP due to oligosaccharides)
            - "sočivo" = lentil (HIGH FODMAP due to oligosaccharides)
            - "čips" = chips
            - "pšenica/pšenična" = wheat (HIGH FODMAP due to fructans)
            - "bezglutenski" = gluten-free (usually LOW FODMAP)
            - "keks" = biscuit/cookie
            - "brašno" = flour
            - "pirinač/riža" = rice (LOW FODMAP)
            - "krompir/krumpir" = potato (LOW FODMAP)

            Product Name: {$product->name}
            Category: {$product->category}

            STEP 1: Translate the product name to English first
            STEP 2: Determine if it's food or non-food
            STEP 3: If food, classify FODMAP level

            Classification Rules:
            - LOW: Food products that are generally safe for people with IBS (less than threshold amounts of FODMAPs)
            - HIGH: Food products that contain significant amounts of FODMAPs (fructans, lactose, fructose, polyols, etc.)
            - NA: Non-food products (cosmetics, cleaning products, toiletries, household items, etc.)
            - UNKNOWN: Food products where you cannot determine the FODMAP level with confidence

            Common HIGH FODMAP foods: wheat products, onions, garlic, beans, milk products, apples, pears, stone fruits, etc.
            Common LOW FODMAP foods: rice, potatoes, carrots, spinach, chicken, fish, lactose-free dairy, oranges, strawberries, COCONUT, etc.

            EXAMPLE ANALYSIS:
            "Kokos komad" = "Coconut piece" = FOOD = Coconut is LOW FODMAP = ANSWER: "low"
            "Instant kafa" = "Instant coffee" = FOOD = Coffee is LOW FODMAP = ANSWER: "low"
            "Pšenični hleb" = "Wheat bread" = FOOD = Wheat is HIGH FODMAP = ANSWER: "high"

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
            You are a FODMAP classification expert. Classify each product based on FODMAP content.

            CONTEXT: These are real products sold on Glovo delivery app in Serbia. Product names are in Serbian/Bosnian/Croatian/Montenegrin languages. Use the category field to help understand what type of product it is.

            Key Serbian food terms to recognize:
            - "čips/chips" = chips/crisps, "keks" = biscuit/cookie, "kreker" = cracker
            - "bezglutenski/gluten free/GF" = gluten-free (usually LOW FODMAP)
            - "pšenica/pšenična" = wheat (HIGH FODMAP), "ječam" = barley (HIGH FODMAP)
            - "mlijeko/mleko" = milk (HIGH FODMAP), "jogurt" = yogurt (HIGH FODMAP)
            - "luk" = onion (HIGH FODMAP), "beli luk/češnjak" = garlic (HIGH FODMAP)
            - "pasulj" = beans (HIGH FODMAP), "sočivo" = lentils (HIGH FODMAP)
            - "pirinač/riža" = rice (LOW FODMAP), "krompir" = potato (LOW FODMAP)
            - "meso/mesa" = meat (LOW FODMAP), "riba" = fish (LOW FODMAP)
            - "alkohol/rakija/vino/pivo" = alcoholic beverages

            Products to classify:
            {$productList}

            Classification approach:
            1. Use the CATEGORY to understand the product type (e.g., "Gluten free" category = likely LOW FODMAP)
            2. Identify main ingredients from the Serbian product name
            3. Apply FODMAP knowledge to classify

            Classification rules:
            - LOW: Safe for IBS (rice, potatoes, meat, fish, eggs, most vegetables, gluten-free products, plain alcohol)
            - HIGH: Contains significant FODMAPs (wheat/grain products, milk/dairy, onion, garlic, beans/legumes, apples, pears)
            - NA: Non-food items (cosmetics, cleaning products, toiletries, household items)
            - UNKNOWN: Food but ingredients unclear or complex formulations

            IMPORTANT: 
            - Products in "Gluten free" category are usually LOW FODMAP
            - Simple meat, fish, vegetable products are usually LOW FODMAP
            - Plain alcoholic beverages (rakija, vodka, wine) are usually LOW FODMAP
            - Be confident - most single-ingredient or simple products can be classified

            Respond ONLY in this exact format:
            1: low
            2: high
            3: na

            Be decisive based on category context and main ingredients.
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

    /**
     * Check if we can make an API call without exceeding rate limits.
     */
    private function canMakeApiCall(): bool
    {
        return $this->getCurrentCallCount() < self::MAX_CALLS_PER_MINUTE;
    }

    /**
     * Get current number of API calls in the current minute window.
     */
    private function getCurrentCallCount(): int
    {
        return (int) Cache::get(self::RATE_LIMIT_KEY, 0);
    }

    /**
     * Increment the API call counter.
     */
    private function incrementCallCount(): void
    {
        $currentCount = $this->getCurrentCallCount();

        if ($currentCount === 0) {
            // First call in the window, set TTL to the window duration
            Cache::put(self::RATE_LIMIT_KEY, 1, self::RATE_LIMIT_WINDOW);
        } else {
            // Increment existing counter, preserve existing TTL
            Cache::increment(self::RATE_LIMIT_KEY);
        }
    }
}
