<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClassifyProductsRequest;
use App\Http\Requests\Api\V1\GetProductStatusRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Jobs\ClassifyProductsJob;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Submit new products for background classification.
     */
    public function submit(ClassifyProductsRequest $request): JsonResponse
    {
        $incomingProducts = collect($request->validated()['products']);
        $externalIds      = $incomingProducts->pluck('externalId')->unique();

        // Check which products already exist
        $existingExternalIds = Product::whereIn('external_id', $externalIds)->pluck('external_id');
        $newProductsData     = $incomingProducts->whereNotIn('externalId', $existingExternalIds);

        if ($newProductsData->isEmpty()) {
            return response()->json([
                'submitted' => 0,
                'message'   => 'All products already exist in the database.',
            ]);
        }

        // Prepare job data for background classification
        $jobData = $newProductsData->map(fn ($data): array => [
            'external_id' => $data['externalId'],
            'name'        => $data['name'],
            'category'    => $data['category'] ?? 'Uncategorized',
        ])->toArray();

        // Dispatch background classification job
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

        return response()->json([
            'submitted' => $newProductsData->count(),
            'message'   => 'Products queued for classification. Use the status endpoint to check progress.',
        ]);
    }

    /**
     * Get current classification status for products by external IDs.
     */
    public function status(GetProductStatusRequest $request): JsonResponse
    {
        $externalIds = $request->validated()['external_ids'];

        // Get products by external IDs
        $products = Product::whereIn('external_id', $externalIds)->get();

        $foundIds   = $products->pluck('external_id')->toArray();
        $missingIds = array_diff($externalIds, $foundIds);

        return response()->json([
            'results'     => ProductResource::collection($products),
            'found'       => $products->count(),
            'missing'     => count($missingIds),
            'missing_ids' => $missingIds,
        ]);
    }
}
