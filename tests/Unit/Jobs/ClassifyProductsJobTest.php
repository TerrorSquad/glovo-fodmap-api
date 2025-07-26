<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Helpers\ProductHashHelper;
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
        /** @var Product $product1 */
        $product1 = Product::factory()->create([
            'name'         => fake()->unique()->word(),
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);

        $product1->name_hash = ProductHashHelper::getProductHash($product1->name);
        $product1->save();

        /** @var Product $product2 */
        $product2 = Product::factory()->create([
            'name'         => fake()->unique()->word(),
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);

        $product2->name_hash = ProductHashHelper::getProductHash($product2->name);
        $product2->save();

        // Create already processed product (should be ignored)
        Product::factory()->create([
            'name'         => fake()->unique()->word(),
            'status'       => 'LOW',
            'processed_at' => now(),
        ])->each(function ($product): void {
            $product->name_hash = ProductHashHelper::getProductHash($product->name);
            $product->save();
        });

        $mockClassifier = $this->createMock(FodmapClassifierInterface::class);
        $mockClassifier->expects($this->once())
            ->method('classifyBatch')
            ->with($this->callback(fn ($products): bool => count($products) === 2
                && collect($products)->pluck('name_hash')->contains($product1->name_hash)
                && collect($products)->pluck('name_hash')->contains($product2->name_hash)))
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

    // TODO: FIX this test
    // public function testJobLimitsProductsToConfiguredBatchSize(): void
    // {
    //     // Create more products than the batch size (50)
    //     $productss = Product::factory()->count(1)->create([
    //         'name' => fake()->unique()->words(6, true),
    //         'status' => 'PENDING',
    //         'processed_at' => null,
    //     ]);
    //     foreach ($productss as $product) {
    //         $product->name_hash = ProductHashHelper::getProductHash($product->name);
    //         $product->save();
    //     }
    //     unset($productss);
    //     unset($product);

    //     $mockClassifier = $this->createMock(FodmapClassifierInterface::class);
    //     $mockClassifier->expects($this->once())
    //         ->method('classifyBatch')
    //         ->with($this->callback(fn($products): bool
    //             // Should only process up to batch size (50)
    //             => count($products) === 50))
    //         ->willReturnCallback(function ($products): array {
    //             $results = [];
    //             foreach ($products as $product) {
    //                 $results[$product->name_hash] = [
    //                     'status' => 'LOW',
    //                     'is_food' => true,
    //                     'explanation' => 'Low FODMAP test result',
    //                 ];
    //             }

    //             return $results;
    //         })
    //     ;

    //     // Bind mock in container
    //     $this->app->instance(FodmapClassifierInterface::class, $mockClassifier);

    //     $job = new ClassifyProductsJob();
    //     $job->handle($mockClassifier);

    //     // Job no longer dispatches itself - scheduled command handles that
    //     Queue::assertNothingPushed();
    // }

    public function testJobProcessesOldestProductsFirst(): void
    {
        // Create products with different creation times
        $newerProduct = Product::factory()->create([
            'name'         => fake()->unique()->word(),
            'name_hash'    => 'name_newer',
            'status'       => 'PENDING',
            'processed_at' => null,
            'created_at'   => now(),
        ]);

        $olderProduct = Product::factory()->create([
            'name'         => fake()->unique()->word(),
            'name_hash'    => 'name_older',
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
