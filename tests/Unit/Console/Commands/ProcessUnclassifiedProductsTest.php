<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Helpers\ProductHashHelper;
use App\Jobs\ClassifyProductsJob;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ProcessUnclassifiedProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function testCommandWithNoUnclassifiedProducts(): void
    {
        $this->artisan('products:process-unclassified')
            ->expectsOutput('No unclassified products found.')
            ->assertExitCode(0)
        ;

        Queue::assertNothingPushed();
    }

    public function testCommandDispatchesJobWithUnclassifiedProducts(): void
    {
        // Create some unclassified products
        Product::factory()->create([
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);

        Product::factory()->create([
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);

        $this->artisan('products:process-unclassified')
            ->expectsOutput('Found 2 unclassified products.')
            ->expectsOutput('Dispatching classification job to queue...')
            ->expectsOutput('Job dispatched successfully. Products will be processed in the background.')
            ->assertExitCode(0)
        ;

        Queue::assertPushed(ClassifyProductsJob::class);
    }

    public function testCommandIgnoresAlreadyProcessedProducts(): void
    {
        // Create processed products
        $product1 = Product::factory()->create([
            'name'         => fake()->unique()->words(3, true),
            'status'       => 'LOW',
            'processed_at' => now(),
        ]);

        $product1->name_hash = ProductHashHelper::getProductHash($product1->name);
        $product1->save();

        $product2 = Product::factory()->create([
            'name'         => fake()->unique()->words(3, true),
            'status'       => 'HIGH',
            'processed_at' => now(),
        ]);

        $product2->name_hash = ProductHashHelper::getProductHash($product2->name);
        $product2->save();

        // Create one unclassified product
        $product3 = Product::factory()->create([
            'name'         => fake()->unique()->words(3, true),
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);

        $product3->name_hash = ProductHashHelper::getProductHash($product3->name);
        $product3->save();

        $this->artisan('products:process-unclassified')
            ->expectsOutput('Found 1 unclassified products.')
            ->expectsOutput('Dispatching classification job to queue...')
            ->expectsOutput('Job dispatched successfully. Products will be processed in the background.')
            ->assertExitCode(0)
        ;

        Queue::assertPushed(ClassifyProductsJob::class);
    }
}
