<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiFodmapClassifierService implements FodmapClassifierInterface
{
    private const RATE_LIMIT_KEY = 'gemini_api_calls';

    private const MAX_CALLS_PER_MINUTE = 60; // Increased for better performance - 1 request per second

    // Conservative rate limiting for stability
    private const RATE_LIMIT_WINDOW = 60; // seconds

    public function classify(Product $product): array
    {
        // Check if API key is available
        $apiKey = config('gemini.api_key');
        if (empty($apiKey)) {
            Log::warning('Gemini API key not configured', [
                'product_name' => $product->name,
            ]);

            return [
                'status'      => 'UNKNOWN',
                'is_food'     => null,
                'explanation' => 'API key not configured',
            ];
        }

        // Check rate limit before making API call
        if (! $this->canMakeApiCall()) {
            Log::warning('Gemini API rate limit reached, waiting before retry', [
                'product_name'  => $product->name,
                'current_calls' => $this->getCurrentCallCount(),
            ]);

            // Wait 2 seconds before proceeding
            sleep(2);
        }

        try {
            $this->incrementCallCount();

            $prompt = $this->buildPrompt($product);

            $geminiClient = \Gemini::client($apiKey);

            $result = $geminiClient->generativeModel('models/gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);

            $classification = trim($result->text());

            // Parse the structured response
            $parsedResult = $this->parseClassificationResponse($classification);

            // Debug logging for Serbian products
            Log::info('Gemini classification debug', [
                'product_name'   => $product->name,
                'category'       => $product->category,
                'raw_response'   => $classification,
                'parsed_result'  => $parsedResult,
                'api_calls_used' => $this->getCurrentCallCount(),
            ]);

            return $parsedResult;
        } catch (\Exception $exception) {
            Log::error('Gemini classification failed', [
                'product_name' => $product->name,
                'error'        => $exception->getMessage(),
            ]);

            // Fallback to safe classification
            return [
                'status'      => 'UNKNOWN',
                'is_food'     => null,
                'explanation' => 'Classification failed: ' . $exception->getMessage(),
            ];
        }
    }

    public function classifyBatch(array $products): array
    {
        if ($products === []) {
            return [];
        }

        // For single product, use individual classification
        if (count($products) === 1) {
            $result = $this->classify($products[0]);

            return [
                $products[0]->external_id => $result,
            ];
        }

        // For queue jobs, guarantee batch processing - wait for rate limit
        $this->waitForRateLimit();

        try {
            $this->incrementCallCount();

            $prompt = $this->buildBatchPrompt($products);

            $geminiClient = \Gemini::client(config('gemini.api_key'));

            $result = $geminiClient->generativeModel('models/gemini-2.5-flash-lite-preview-06-17')->generateContent($prompt);

            $response = trim($result->text());

            $results = $this->parseBatchResponse($response, $products);

            Log::info('Gemini batch classification completed', [
                'product_count'    => count($products),
                'api_calls_used'   => $this->getCurrentCallCount(),
                'classified_count' => count($results),
            ]);

            return $results;
        } catch (\Exception $exception) {
            Log::error('Gemini batch classification failed', [
                'product_count' => count($products),
                'error'         => $exception->getMessage(),
            ]);

            // For queue jobs, maintain batch processing by returning UNKNOWN for all
            // Individual classification can still fallback to individual calls
            $fallbackResults = [];
            foreach ($products as $product) {
                $fallbackResults[$product->external_id] = [
                    'status'      => 'UNKNOWN',
                    'is_food'     => null,
                    'explanation' => 'Batch classification failed: ' . $exception->getMessage(),
                ];
            }

            return $fallbackResults;
        }
    }

    private function buildPrompt(Product $product): string
    {
        return <<<PROMPT
            You are a FODMAP classification expert. Classify the following product based on FODMAP content.

            CRITICAL: Product names are in Serbian/Bosnian/Croatian/Montenegrin language. You MUST translate and understand them first.

            Translation dictionary:
            - "liker" = liqueur (ALCOHOLIC BEVERAGE - usually LOW FODMAP)
            - "rakija" = brandy/rakia (ALCOHOLIC BEVERAGE - LOW FODMAP)
            - "kulen" = kulen sausage (MEAT PRODUCT - LOW FODMAP)
            - "slajs" = slices (LOW FODMAP)
            - "kobasica" = sausage (MEAT PRODUCT - LOW FODMAP)
            - "mesne prerađevine" = meat products (LOW FODMAP)
            - "ostala žestoka pića" = other spirits/alcoholic beverages (LOW FODMAP)
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

            Classification Rules for Mixed Ingredients:
            - If a product contains BOTH low and high FODMAP ingredients, classify based on the DOMINANT ingredient
            - For processed foods (pizza, pastries, mixed dishes), classify as HIGH if wheat/gluten is present
            - For products with small amounts of high FODMAP ingredients (spices, seasonings), classify as MODERATE
            - Only use UNKNOWN if ingredients are truly unclear or very complex formulations

            FODMAP Classification:
            - LOW: Food products that are generally safe for people with IBS (less than threshold amounts of FODMAPs)
            - MODERATE: Food products with small amounts of FODMAPs that may be tolerated in limited portions
            - HIGH: Food products that contain significant amounts of FODMAPs (fructans, lactose, fructose, polyols, etc.)
            - NA: Non-food products (cosmetics, cleaning products, toiletries, household items, etc.)
            - UNKNOWN: Food products where ingredients are truly unclear (avoid this unless absolutely necessary)

            Common HIGH FODMAP foods: wheat products, onions, garlic, beans, milk products, apples, pears, stone fruits, etc.
            Common LOW FODMAP foods: rice, potatoes, carrots, spinach, chicken, fish, lactose-free dairy, oranges, strawberries, COCONUT, MEAT PRODUCTS, ALCOHOLIC BEVERAGES, etc.

            IMPORTANT CLASSIFICATIONS:
            - ALL MEAT PRODUCTS (kulen, kobasica, sausages, etc.) = LOW FODMAP
            - ALL ALCOHOLIC BEVERAGES (liker, rakija, vodka, wine, beer, etc.) = LOW FODMAP
            - SIMPLE VEGETABLES AND FRUITS (except high FODMAP ones) = LOW FODMAP
            - WHEAT-BASED PRODUCTS (bread, pasta, pizza dough) = HIGH FODMAP
            - DAIRY PRODUCTS (milk, yogurt, soft cheese) = HIGH FODMAP

            EXAMPLE ANALYSIS:
            "Kokos komad" = "Coconut piece" = FOOD = Coconut is LOW FODMAP = ANSWER: "LOW"
            "Pizza sa sirom" = "Cheese pizza" = FOOD = Contains wheat (high) + cheese (high) = ANSWER: "HIGH"
            "Piletina sa rižom" = "Chicken with rice" = FOOD = Chicken (low) + rice (low) = ANSWER: "LOW"

            Return response as JSON with Serbian explanation:
            {
                "status": "LOW|MODERATE|HIGH|NA|UNKNOWN",
                "is_food": true|false,
                "explanation": "Kratko objašnjenje na srpskom jeziku zašto je proizvod klasifikovan ovako"
            }

            IMPORTANT: Write explanation in Serbian language. Be decisive - avoid UNKNOWN unless truly necessary.
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
            - "liker/rakija" = alcoholic beverages (LOW FODMAP)
            - "kulen/kobasica/slajs" = meat products/sausages (LOW FODMAP)
            - "mesne prerađevine" = meat products category (LOW FODMAP)
            - "ostala žestoka pića" = alcoholic beverages category (LOW FODMAP)
            - "čips/chips" = chips/crisps, "keks" = biscuit/cookie, "kreker" = cracker
            - "bezglutenski/gluten free/GF" = gluten-free (usually LOW FODMAP)
            - "pšenica/pšenična" = wheat (HIGH FODMAP), "ječam" = barley (HIGH FODMAP)
            - "mlijeko/mleko" = milk (HIGH FODMAP), "jogurt" = yogurt (HIGH FODMAP)
            - "luk" = onion (HIGH FODMAP), "beli luk/češnjak" = garlic (HIGH FODMAP)
            - "pasulj" = beans (HIGH FODMAP), "sočivo" = lentils (HIGH FODMAP)
            - "pirinač/riža" = rice (LOW FODMAP), "krompir" = potato (LOW FODMAP)
            - "meso/mesa" = meat (LOW FODMAP), "riba" = fish (LOW FODMAP)

            Products to classify:
            {$productList}

            Classification approach for mixed ingredients:
            1. Use the CATEGORY to understand the product type (e.g., "Gluten free" category = likely LOW FODMAP)
            2. Identify main ingredients from the Serbian product name
            3. For products with BOTH low and high FODMAP ingredients, classify based on DOMINANT ingredient
            4. For wheat-based products (pizza, bread, pastries), classify as HIGH even if other ingredients are low FODMAP
            5. For products with small amounts of high FODMAP seasonings, classify as MODERATE

            FODMAP Classification Rules:
            - LOW: Safe for IBS (rice, potatoes, meat, fish, eggs, most vegetables, gluten-free products, plain alcohol)
            - MODERATE: Small amounts of FODMAPs that may be tolerated in limited portions (products with minor high FODMAP seasonings)
            - HIGH: Contains significant FODMAPs (wheat/grain products, milk/dairy, onion, garlic, beans/legumes, apples, pears)
            - NA: Non-food items (cosmetics, cleaning products, toiletries, household items)
            - UNKNOWN: Food but ingredients truly unclear (AVOID - be decisive based on main ingredients)

            IMPORTANT Classification Guidelines:
            - Products in "Mesne Prerađevine" (meat products) category = LOW FODMAP
            - Products in "Ostala Žestoka Pića" (alcoholic beverages) category = LOW FODMAP
            - Products in "Gluten free" category = usually LOW FODMAP
            - Simple meat, fish, vegetable products = usually LOW FODMAP
            - Plain alcoholic beverages (rakija, vodka, wine, liker) = LOW FODMAP
            - Wheat-based products (pizza, bread, pasta) = HIGH FODMAP regardless of other ingredients
            - Dairy products (milk, yogurt, soft cheese) = HIGH FODMAP
            - BE DECISIVE - classify based on dominant ingredient, avoid UNKNOWN

            Respond with valid JSON array with Serbian explanations:
            [
                {
                    "external_id": "product_external_id_1",
                    "status": "LOW|MODERATE|HIGH|NA|UNKNOWN",
                    "is_food": true|false,
                    "explanation": "Kratko objašnjenje na srpskom jeziku"
                },
                {
                    "external_id": "product_external_id_2",
                    "status": "LOW|MODERATE|HIGH|NA|UNKNOWN",
                    "is_food": true|false,
                    "explanation": "Kratko objašnjenje na srpskom jeziku"
                }
            ]

            IMPORTANT: Write all explanations in Serbian language. Be decisive - avoid UNKNOWN classification.
            PROMPT;
    }

    private function parseBatchResponse(string $response, array $products): array
    {
        $results = [];

        try {
            // Clean the response - remove markdown code blocks if present
            $cleanResponse = trim($response);
            $cleanResponse = preg_replace('/^```json\s*/', '', $cleanResponse);
            $cleanResponse = preg_replace('/\s*```$/', '', (string) $cleanResponse);
            $cleanResponse = trim((string) $cleanResponse);

            $jsonResponse = json_decode($cleanResponse, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($jsonResponse)) {
                throw new \Exception('Response is not an array');
            }

            foreach ($jsonResponse as $item) {
                if (isset($item['external_id'], $item['status'])) {
                    $results[$item['external_id']] = [
                        'status'      => strtoupper((string) $item['status']),
                        'is_food'     => $item['is_food']     ?? true,
                        'explanation' => $item['explanation'] ?? null,
                    ];
                }
            }

            // Fill missing results with fallback for each product
            foreach ($products as $product) {
                if (! isset($results[$product->external_id])) {
                    $results[$product->external_id] = [
                        'status'      => 'UNKNOWN',
                        'is_food'     => true,
                        'explanation' => 'No classification result received',
                    ];
                    Log::warning('Missing classification result for product', [
                        'external_id'  => $product->external_id,
                        'product_name' => $product->name,
                    ]);
                }
            }
        } catch (\Exception $exception) {
            Log::error('Failed to parse batch classification response', [
                'error'    => $exception->getMessage(),
                'response' => substr($response, 0, 500),
            ]);

            // Fallback: return UNKNOWN for all products
            foreach ($products as $product) {
                $results[$product->external_id] = [
                    'status'      => 'UNKNOWN',
                    'is_food'     => true,
                    'explanation' => 'Failed to parse classification response',
                ];
            }
        }

        return $results;
    }

    /**
     * Parse single product classification response.
     */
    private function parseClassificationResponse(string $response): array
    {
        try {
            // Handle JSON wrapped in markdown code blocks
            $cleanResponse = trim($response);
            if (str_contains($cleanResponse, '```json')) {
                $cleanResponse = preg_replace('/```json\s*/', '', $cleanResponse);
                $cleanResponse = preg_replace('/\s*```/', '', (string) $cleanResponse);
                $cleanResponse = trim((string) $cleanResponse);
            }

            $jsonResponse = json_decode($cleanResponse, true, 512, JSON_THROW_ON_ERROR);

            return [
                'status'      => strtoupper($jsonResponse['status'] ?? 'UNKNOWN'),
                'is_food'     => $jsonResponse['is_food']     ?? true,
                'explanation' => $jsonResponse['explanation'] ?? null,
            ];
        } catch (\Exception $exception) {
            Log::error('Failed to parse classification response', [
                'error'    => $exception->getMessage(),
                'response' => substr($response, 0, 200),
            ]);

            // Fallback to legacy parsing
            return $this->legacyParseClassification($response);
        }
    }

    /**
     * Legacy parsing for backward compatibility.
     */
    private function legacyParseClassification(string $response): array
    {
        $status = $this->normalizeClassification($response);

        return [
            'status'      => $status,
            'is_food'     => $status !== 'NA',
            'explanation' => null,
        ];
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

    /**
     * Wait for rate limit to allow API calls.
     */
    private function waitForRateLimit(int $maxAttempts = 30): void
    {
        $attempts = 0;
        while (! $this->canMakeApiCall() && $attempts < $maxAttempts) {
            Log::info('Waiting for rate limit', [
                'attempt'       => $attempts + 1,
                'current_calls' => $this->getCurrentCallCount(),
                'max_calls'     => self::MAX_CALLS_PER_MINUTE,
            ]);

            sleep(2);
            ++$attempts;
        }

        if (! $this->canMakeApiCall()) {
            Log::warning('Rate limit wait timeout reached', [
                'max_attempts'  => $maxAttempts,
                'current_calls' => $this->getCurrentCallCount(),
            ]);
        }
    }
}
