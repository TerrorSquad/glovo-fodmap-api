<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClassifyProductsRequest;
use App\Http\Requests\Api\V1\GetProductStatusRequest;
use App\Http\Resources\Api\V1\ProductStatusResource;
use App\Jobs\ClassifyProductsJob;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Glovo FODMAP API',
    description: 'API for classifying food products as LOW, MODERATE, or HIGH FODMAP using Google Gemini AI'
)]
#[OA\Server(
    url: 'https://glovo-fodmap-api.fly.dev/api',
    description: 'Production server'
)]
#[OA\Server(
    url: 'http://localhost:8000/api',
    description: 'Development server'
)]
#[OA\Tag(
    name: 'Products',
    description: 'Operations for food product FODMAP classification'
)]
class ProductController extends Controller
{
    /**
     * Submit new products for background classification.
     */
    #[OA\Post(
        path: '/v1/products/submit',
        summary: 'Submit products for FODMAP classification',
        description: 'Submit one or more products to be classified as LOW, MODERATE, or HIGH FODMAP in the background. Products are processed asynchronously using AI classification.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProductSubmissionRequest')
        ),
        tags: ['Products'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Products successfully submitted for classification',
                content: new OA\JsonContent(ref: '#/components/schemas/SubmissionResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
        ]
    )]
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
    #[OA\Post(
        path: '/v1/products/status',
        summary: 'Get product classification status',
        description: 'Check the current FODMAP classification status for products by their external IDs. Returns detailed information about found products and lists any missing IDs.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProductStatusRequest')
        ),
        tags: ['Products'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product status information',
                content: new OA\JsonContent(ref: '#/components/schemas/ProductStatusResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')
            ),
        ]
    )]
    public function status(GetProductStatusRequest $request): JsonResponse
    {
        $externalIds = $request->validated()['external_ids'];

        // Get products by external IDs
        $products = Product::whereIn('external_id', $externalIds)->get();

        $foundIds   = $products->pluck('external_id')->toArray();
        $missingIds = array_diff($externalIds, $foundIds);

        $statusData = [
            'results'     => $products,
            'found'       => $products->count(),
            'missing'     => count($missingIds),
            'missing_ids' => $missingIds,
        ];

        return response()->json(new ProductStatusResource($statusData));
    }
}
