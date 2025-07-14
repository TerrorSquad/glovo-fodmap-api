<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int    $id
 * @property string $external_id
 * @property string $name
 * @property string $category
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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
            'id'         => $this->id,
            'externalId' => $this->external_id,
            'name'       => $this->name,
            'category'   => $this->category,
            'status'     => $this->status,
            'createdAt'  => $this->created_at,
            'updatedAt'  => $this->updated_at,
        ];
    }
}
