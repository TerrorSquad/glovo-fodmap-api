<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\ProductHashHelper;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Command to deduplicate products by hash, keeping the earliest created product.
 */
class DeduplicateProductsByHash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:deduplicate-by-hash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate products by hash, keeping the earliest created.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting deduplication of products by hash...');
        $duplicates = [];
        $hashMap    = [];
        $products   = Product::orderBy('created_at')->get();
        foreach ($products as $product) {
            $hash = ProductHashHelper::getProductHash($product->name ?? '');
            if (! isset($hashMap[$hash])) {
                $hashMap[$hash] = $product->id;
            } else {
                $duplicates[] = $product->id;
            }
        }

        if ($duplicates !== []) {
            Product::whereIn('id', $duplicates)->delete();
            $this->info('Deleted ' . count($duplicates) . ' duplicate products.');
        } else {
            $this->info('No duplicates found.');
        }

        return 0;
    }
}
