<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class DocsController extends Controller
{
    /**
     * Health check endpoint.
     */
    #[OA\Get(
        path: '/health',
        summary: 'Health check',
        description: 'Check if the API is running and healthy',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'API is healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'version', type: 'string', example: '1.0.0'),
                    ]
                )
            ),
        ]
    )]
    public function health(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toISOString(),
            'version'   => config('app.version', '1.0.0'),
        ]);
    }

    /**
     * Get the OpenAPI specification in JSON format.
     */
    #[OA\Get(
        path: '/docs/openapi.json',
        summary: 'Get OpenAPI specification (JSON)',
        description: 'Returns the complete OpenAPI 3.0 specification for this API in JSON format',
        tags: ['Documentation'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OpenAPI specification in JSON format',
                content: new OA\JsonContent(
                    type: 'object',
                    example: [
                        'openapi' => '3.0.0',
                        'info'    => [
                            'title'   => 'Glovo FODMAP API',
                            'version' => '1.0.0',
                        ],
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'OpenAPI specification file not found'
            ),
        ]
    )]
    public function openApiJson(): JsonResponse
    {
        $specPath = base_path('documentation/openapi.json');

        if (! file_exists($specPath)) {
            return response()->json([
                'error' => 'OpenAPI specification not found',
            ], 404);
        }

        $jsonContent = file_get_contents($specPath);
        $spec        = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'error' => 'Invalid OpenAPI specification format',
            ], 500);
        }

        return response()->json($spec);
    }

    /**
     * Get the OpenAPI specification in YAML format.
     */
    #[OA\Get(
        path: '/docs/openapi.yaml',
        summary: 'Get OpenAPI specification (YAML)',
        description: 'Returns the complete OpenAPI 3.0 specification for this API in YAML format',
        tags: ['Documentation'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OpenAPI specification in YAML format',
                content: new OA\MediaType(
                    mediaType: 'text/yaml',
                    schema: new OA\Schema(type: 'string')
                )
            ),
            new OA\Response(
                response: 404,
                description: 'OpenAPI specification file not found'
            ),
        ]
    )]
    public function openApiYaml(): Response
    {
        $specPath = base_path('documentation/openapi.yml');

        if (! file_exists($specPath)) {
            return response('OpenAPI specification not found', 404);
        }

        $yamlContent = file_get_contents($specPath);

        return response($yamlContent, 200, [
            'Content-Type' => 'text/yaml',
        ]);
    }
}
