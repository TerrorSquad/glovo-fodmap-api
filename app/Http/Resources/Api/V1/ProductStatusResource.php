<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductStatusResponse',
    title: 'Product Status Response',
    description: 'Response containing product status information with found and missing products',
    properties: [
        new OA\Property(
            property: 'results',
            description: 'Array of found products with their classification status',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ProductResource')
        ),
        new OA\Property(property: 'found', description: 'Number of products found', type: 'integer', example: 2),
        new OA\Property(property: 'missing', description: 'Number of products not found', type: 'integer', example: 0),
        new OA\Property(
            property: 'missing_ids',
            description: 'Array of external IDs that were not found',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: []
        ),
    ]
)]
class ProductStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'results'     => ProductResource::collection($this->resource['products']),
            'found'       => $this->resource['found'],
            'missing'     => $this->resource['missing'],
            'missing_ids' => $this->resource['missing_ids'],
        ];
    }
}
