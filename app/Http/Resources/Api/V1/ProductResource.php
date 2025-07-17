<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/**
 * @property int         $id
 * @property string      $external_id
 * @property string      $name
 * @property string      $category
 * @property string      $status
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property null|Carbon $processed_at
 */
#[OA\Schema(
    schema: 'ProductResource',
    title: 'Product Resource',
    description: 'Product resource response with FODMAP classification status',
    properties: [
        new OA\Property(property: 'id', description: 'Internal database ID', type: 'integer', example: 123),
        new OA\Property(property: 'externalId', description: 'External system identifier', type: 'string', example: 'glovo-123'),
        new OA\Property(property: 'name', description: 'Product name', type: 'string', example: 'Banana'),
        new OA\Property(property: 'category', description: 'Product category', type: 'string', example: 'Fruit'),
        new OA\Property(
            property: 'status',
            description: 'FODMAP classification status: PENDING (not yet processed), LOW (safe for IBS), MODERATE (limited portions), HIGH (avoid), UNKNOWN (classification failed)',
            type: 'string',
            enum: ['PENDING', 'LOW', 'MODERATE', 'HIGH', 'UNKNOWN'],
            example: 'LOW'
        ),
        new OA\Property(property: 'createdAt', description: 'When the product was first submitted', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', description: 'When the product was last updated', type: 'string', format: 'date-time'),
        new OA\Property(property: 'processedAt', description: 'When the product was classified (null if still pending)', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'externalId'  => $this->external_id,
            'name'        => $this->name,
            'category'    => $this->category,
            'status'      => $this->status,
            'createdAt'   => $this->created_at,
            'updatedAt'   => $this->updated_at,
            'processedAt' => $this->processed_at,
        ];
    }
}
