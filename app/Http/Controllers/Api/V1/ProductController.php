<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClassifyProductsRequest;
use App\Models\Product;
use App\Services\FodmapClassifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function classify(
        ClassifyProductsRequest $request,
        FodmapClassifierService $classifier
    ): JsonResponse {
        $incomingProducts = $request->validated()['products'];
        $externalIds      = array_unique(array_column($incomingProducts, 'externalId'));

        $productsToUpsert = array_map(
            fn ($data): array => [
                'external_id' => $data['externalId'],
                'name'        => $data['name'],
                'category'    => $data['category'] ?? 'Uncategorized',
                'status'      => $classifier->classify(new Product([
                    'name'     => $data['name'],
                    'category' => $data['category'] ?? 'Uncategorized',
                ])),
            ],
            $incomingProducts
        );

        Product::upsert(
            $productsToUpsert,
            ['external_id'],
            ['name', 'category', 'status']
        );

        $finalResults = Product::whereIn('external_id', $externalIds)->get();

        return response()->json([
            'results' => $finalResults,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void {}

    /**
     * Display the specified resource.
     */
    public function show(string $id): void {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): void {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): void {}
}
