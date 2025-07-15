<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClassifyProductsRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Jobs\ClassifyProductsJob;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function classify(ClassifyProductsRequest $request): JsonResponse
    {
        $incomingProducts = collect($request->validated()['products']);
        $externalIds      = $incomingProducts->pluck('externalId')->unique();

        // Get existing products (fast database lookup)
        $existingProducts    = Product::whereIn('external_id', $externalIds)->get();
        $existingExternalIds = $existingProducts->pluck('external_id');

        // Identify new products that need classification
        $newProductsData = $incomingProducts->whereNotIn('externalId', $existingExternalIds);

        // Dispatch background job for new products (if any)
        if ($newProductsData->isNotEmpty()) {
            $jobData = $newProductsData->map(fn ($data): array => [
                'external_id' => $data['externalId'],
                'name'        => $data['name'],
                'category'    => $data['category'] ?? 'Uncategorized',
            ])->toArray();

            ClassifyProductsJob::dispatch($jobData);

            // Create placeholder records immediately for better UX
            $placeholderProducts = [];
            foreach ($newProductsData as $data) {
                $placeholderProducts[] = [
                    'external_id' => $data['externalId'],
                    'name'        => $data['name'],
                    'category'    => $data['category'] ?? 'Uncategorized',
                    'status'      => 'PENDING',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            Product::insert($placeholderProducts);
        }

        // Return current status for all requested products (existing + newly created placeholders)
        $finalResults = Product::whereIn('external_id', $externalIds)->get();

        return response()->json([
            'results'               => ProductResource::collection($finalResults),
            'new_products'          => $newProductsData->count(),
            'classification_status' => $newProductsData->isNotEmpty()
                ? 'Products queued for classification. Check back in a few moments.'
                : 'All products already classified.',
        ]);
    }
}
