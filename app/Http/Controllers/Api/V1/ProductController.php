<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClassifyProductsRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Services\FodmapClassifierInterface;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function classify(
        ClassifyProductsRequest $request,
        FodmapClassifierInterface $classifier
    ): JsonResponse {
        $incomingProducts = collect($request->validated()['products']);
        $externalIds      = $incomingProducts->pluck('externalId')->unique();

        $existingProducts    = Product::whereIn('external_id', $externalIds)->get();
        $existingExternalIds = $existingProducts->pluck('external_id');

        $newProductsData = $incomingProducts->whereNotIn('externalId', $existingExternalIds);

        $productsToInsert = [];
        if ($newProductsData->isNotEmpty()) {
            foreach ($newProductsData as $data) {
                $productForClassification = new Product(
                    [
                        'name'     => $data['name'],
                        'category' => $data['category'] ?? 'Uncategorized',
                    ]
                );

                $productsToInsert[] = [
                    'external_id' => $data['externalId'],
                    'name'        => $data['name'],
                    'category'    => $productForClassification->category,
                    'status'      => $classifier->classify($productForClassification),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            Product::insert($productsToInsert);
        }

        $finalResults = Product::whereIn('external_id', $externalIds)->get();

        return response()->json([
            'results' => ProductResource::collection($finalResults),
        ]);
    }
}
