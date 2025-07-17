<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // No special setup needed for testing without authentication
    }

    public function testSubmitProductsWithValidData(): void
    {
        $response = $this->postJson('/api/v1/products/submit', [
            'products' => [
                [
                    'externalId' => 'test-product-1',
                    'name'       => 'Banana',
                    'category'   => 'Fruits',
                ],
                [
                    'externalId' => 'test-product-2',
                    'name'       => 'Apple',
                    'category'   => 'Fruits',
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'submitted',
                'message',
            ])
        ;

        $this->assertDatabaseHas('products', [
            'external_id' => 'test-product-1',
            'name'        => 'Banana',
        ]);
    }

    public function testGetProductStatus(): void
    {
        // Create test products
        $product1 = Product::factory()->create([
            'external_id'  => 'test-1',
            'name'         => 'Banana',
            'status'       => 'LOW',
            'processed_at' => now(),
        ]);

        $product2 = Product::factory()->create([
            'external_id'  => 'test-2',
            'name'         => 'Onion',
            'status'       => 'HIGH',
            'processed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/products/status', [
            'external_ids' => ['test-1', 'test-2'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'results' => [
                    '*' => [
                        'externalId',
                        'name',
                        'status',
                        'createdAt',
                        'updatedAt',
                        'processedAt',
                    ],
                ],
                'found',
                'missing',
                'missing_ids',
            ])
        ;
    }

    public function testSubmitProductsValidation(): void
    {
        $response = $this->postJson('/api/v1/products/submit', [
            'products' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products'])
        ;
    }
}
