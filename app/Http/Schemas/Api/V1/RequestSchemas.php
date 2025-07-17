<?php

declare(strict_types=1);

namespace App\Http\Schemas\Api\V1;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductSubmissionRequest',
    title: 'Product Submission Request',
    description: 'Request payload for submitting products for FODMAP classification',
    required: ['products'],
    properties: [
        new OA\Property(
            property: 'products',
            description: 'Array of products to classify',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ProductInput')
        ),
    ]
)]
#[OA\Schema(
    schema: 'ProductInput',
    title: 'Product Input',
    description: 'Individual product data for classification',
    required: ['externalId', 'name'],
    properties: [
        new OA\Property(property: 'externalId', description: 'Unique external identifier for the product', type: 'string', example: 'glovo-123'),
        new OA\Property(property: 'name', description: 'Product name', type: 'string', example: 'Banana'),
        new OA\Property(property: 'category', description: 'Product category (optional)', type: 'string', example: 'Fruit'),
    ]
)]
#[OA\Schema(
    schema: 'ProductStatusRequest',
    title: 'Product Status Request',
    description: 'Request payload for checking product classification status',
    required: ['external_ids'],
    properties: [
        new OA\Property(
            property: 'external_ids',
            description: 'Array of external IDs to check status for',
            type: 'array',
            items: new OA\Items(type: 'string'),
            example: ['glovo-123', 'glovo-456']
        ),
    ]
)]
#[OA\Schema(
    schema: 'SubmissionResponse',
    title: 'Submission Response',
    description: 'Response after submitting products for classification',
    properties: [
        new OA\Property(property: 'submitted', description: 'Number of products successfully submitted', type: 'integer', example: 3),
        new OA\Property(property: 'message', description: 'Status message', type: 'string', example: 'Products queued for classification. Use the status endpoint to check progress.'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    title: 'Validation Error Response',
    description: 'Error response for validation failures',
    properties: [
        new OA\Property(property: 'message', description: 'Error message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            description: 'Detailed validation errors',
            type: 'object',
            example: [
                'products' => ['The products field is required.'],
            ]
        ),
    ]
)]
class RequestSchemas
{
    // This class only exists to hold the schema annotations
}
