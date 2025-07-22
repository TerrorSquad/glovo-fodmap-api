<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/**
 * @property int         $id
 * @property string      $name_hash
 * @property string      $name
 * @property string      $category
 * @property null|bool   $is_food
 * @property string      $status
 * @property null|string $explanation
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
        new OA\Property(property: 'name', description: 'Product name', type: 'string', example: 'Banana'),
        new OA\Property(property: 'category', description: 'Product category', type: 'string', example: 'Fruit'),
        new OA\Property(property: 'isFood', description: 'Whether this product is food (true) or non-food item (false). Null if not yet classified.', type: 'boolean', example: true, nullable: true),
        new OA\Property(
            property: 'status',
            description: 'FODMAP classification status: PENDING (not yet processed), LOW (safe for IBS), MODERATE (limited portions), HIGH (avoid), UNKNOWN (classification failed)',
            type: 'string',
            enum: ['PENDING', 'LOW', 'MODERATE', 'HIGH', 'UNKNOWN'],
            example: 'LOW'
        ),
        new OA\Property(
            property: 'explanation',
            description: 'Explanation of why the product has this FODMAP classification (e.g., "Contains fructose from natural fruit sugars")',
            type: 'string',
            example: 'Low FODMAP fruit, safe in normal portions',
            nullable: true
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
            'nameHash'    => $this->name_hash,
            'name'        => $this->name,
            'category'    => $this->category,
            'isFood'      => $this->is_food,
            'status'      => $this->status,
            'explanation' => $this->explanation,
            'createdAt'   => $this->created_at,
            'updatedAt'   => $this->updated_at,
            'processedAt' => $this->processed_at,
        ];
    }
}
