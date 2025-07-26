<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
        // Fake the queue to prevent jobs from executing during tests
        Queue::fake();
    }

    public function testSubmitProductsWithValidData(): void
    {
        $response = $this->postJson('/api/v1/products/submit', [
            'products' => [
                [
                    'hash'     => 'name_123456',
                    'name'     => 'Banana',
                    'category' => 'Fruits',
                ],
                [
                    'hash'     => 'name_654321',
                    'name'     => 'Apple',
                    'category' => 'Fruits',
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
            'name_hash' => 'name_123456',
            'name'      => 'Banana',
        ]);

        // Products are now processed by scheduled command, not immediate dispatch
        Queue::assertNothingPushed();
    }

    public function testGetProductStatus(): void
    {
        // Create test products
        $product1 = Product::factory()->create([
            'name_hash'    => 'name_111111',
            'name'         => 'Banana',
            'status'       => 'LOW',
            'processed_at' => now(),
        ]);

        $product2 = Product::factory()->create([
            'name_hash'    => 'name_222222',
            'name'         => 'Onion',
            'status'       => 'HIGH',
            'processed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/products/status', [
            'hashes' => ['name_111111', 'name_222222'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'results' => [
                    '*' => [
                        'nameHash',
                        'name',
                        'status',
                        'createdAt',
                        'updatedAt',
                        'processedAt',
                    ],
                ],
                'found',
                'missing',
                'missingHashes',
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
