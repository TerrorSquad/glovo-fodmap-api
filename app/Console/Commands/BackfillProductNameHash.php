<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\ProductHashHelper;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Command to backfill name_hash for all products.
 */
class BackfillProductNameHash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:backfill-name-hash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill name_hash for all products using ProductHashHelper.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting backfill of name_hash for products...');
        $count = 0;
        Product::query()->chunkById(500, function ($products) use (&$count): void {
            foreach ($products as $product) {
                $hash = ProductHashHelper::getProductHash($product->name ?? '');
                if ($hash !== '' && $product->name_hash !== $hash) {
                    $product->name_hash = $hash;
                    $product->save();
                    ++$count;
                }
            }
        });
        $this->info(sprintf('Backfill complete. Updated %d products.', $count));

        return 0;
    }
}
