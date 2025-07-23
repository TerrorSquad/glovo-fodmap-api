<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\ClassifyProductsJob;
use App\Models\Product;
use App\Services\FodmapClassifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ClassifyProductsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function testJobHandlesNoUnclassifiedProducts(): void
    {
        // Create only processed products
        Product::factory()->create([
            'status'       => 'LOW',
            'processed_at' => now(),
        ]);

        $mockClassifier = $this->createMock(FodmapClassifierInterface::class);
        $mockClassifier->expects($this->never())
            ->method('classifyBatch')
        ;

        $job = new ClassifyProductsJob();
        $job->handle($mockClassifier);

        // Expect no further jobs to be dispatched
        Queue::assertNothingPushed();
    }

    public function testJobProcessesUnclassifiedProducts(): void
    {
        // Create unclassified products
        $product1 = Product::factory()->create([
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);

        $product2 = Product::factory()->create([
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);

        // Create already processed product (should be ignored)
        Product::factory()->create([
            'status'       => 'LOW',
            'processed_at' => now(),
        ]);

        $mockClassifier = $this->createMock(FodmapClassifierInterface::class);
        $mockClassifier->expects($this->once())
            ->method('classifyBatch')
            ->with($this->callback(fn ($products): bool => count($products) === 2
                && collect($products)->pluck('id')->contains($product1->id)
                && collect($products)->pluck('id')->contains($product2->id)))
            ->willReturn([
                $product1->name_hash => [
                    'status'      => 'LOW',
                    'is_food'     => true,
                    'explanation' => 'Low FODMAP',
                ],
                $product2->name_hash => [
                    'status'      => 'HIGH',
                    'is_food'     => true,
                    'explanation' => 'High FODMAP',
                ],
            ])
        ;

        // Bind mock in container
        $this->app->instance(FodmapClassifierInterface::class, $mockClassifier);

        $job = new ClassifyProductsJob();
        $job->handle($mockClassifier);

        // Check that products were updated
        $product1->refresh();
        $product2->refresh();

        $this->assertEquals('LOW', $product1->status);
        $this->assertEquals('HIGH', $product2->status);
        $this->assertNotNull($product1->processed_at);
        $this->assertNotNull($product2->processed_at);
    }

    public function testJobLimitsProductsToConfiguredBatchSize(): void
    {
        // Create more products than the batch size (50)
        Product::factory()->count(75)->create([
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);

        $mockClassifier = $this->createMock(FodmapClassifierInterface::class);
        $mockClassifier->expects($this->once())
            ->method('classifyBatch')
            ->with($this->callback(fn ($products): bool
                // Should only process up to batch size (50)
                => count($products) === 50))
            ->willReturnCallback(function ($products): array {
                $results = [];
                foreach ($products as $product) {
                    $results[$product->name_hash] = [
                        'status'      => 'LOW',
                        'is_food'     => true,
                        'explanation' => 'Low FODMAP test result',
                    ];
                }

                return $results;
            })
        ;

        // Bind mock in container
        $this->app->instance(FodmapClassifierInterface::class, $mockClassifier);

        $job = new ClassifyProductsJob();
        $job->handle($mockClassifier);

        // Job no longer dispatches itself - scheduled command handles that
        Queue::assertNothingPushed();
    }

    public function testJobProcessesOldestProductsFirst(): void
    {
        // Create products with different creation times
        $newerProduct = Product::factory()->create([
            'status'       => 'PENDING',
            'processed_at' => null,
            'created_at'   => now(),
        ]);

        $olderProduct = Product::factory()->create([
            'status'       => 'PENDING',
            'processed_at' => null,
            'created_at'   => now()->subMinutes(10),
        ]);

        $mockClassifier = $this->createMock(FodmapClassifierInterface::class);
        $mockClassifier->expects($this->once())
            ->method('classifyBatch')
            ->with($this->callback(fn ($products): bool
                // First product should be the older one
                => $products[0]->id === $olderProduct->id && $products[1]->id === $newerProduct->id))
            ->willReturn(['LOW', 'HIGH'])
        ;

        $job = new ClassifyProductsJob();
        $job->handle($mockClassifier);
    }
}
