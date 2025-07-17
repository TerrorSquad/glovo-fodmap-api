<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

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
        Product::factory()->create([
            'status'       => 'LOW',
            'processed_at' => now(),
        ]);

        Product::factory()->create([
            'status'       => 'HIGH',
            'processed_at' => now(),
        ]);

        // Create one unclassified product
        Product::factory()->create([
            'status'       => 'PENDING',
            'processed_at' => null,
        ]);

        $this->artisan('products:process-unclassified')
            ->expectsOutput('Found 1 unclassified products.')
            ->expectsOutput('Dispatching classification job to queue...')
            ->expectsOutput('Job dispatched successfully. Products will be processed in the background.')
            ->assertExitCode(0)
        ;

        Queue::assertPushed(ClassifyProductsJob::class);
    }
}
