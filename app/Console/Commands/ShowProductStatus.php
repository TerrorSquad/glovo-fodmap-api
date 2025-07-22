<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class ShowProductStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fodmap:status
                            {--name-hashes= : Show status for specific name_hashes (comma-separated for multiple)}
                            {--status= : Filter by status (low, high, unknown)}
                            {--limit=10 : Number of products to show (default: 10)}
                            {--with-explanation : Show detailed explanations}
                            {--stats : Show classification statistics}';

    /**
     * The console command description.
     */
    protected $description = 'Show FODMAP classification status of products';

    public function handle(): int
    {
        if ($this->option('stats')) {
            $this->showStatistics();

            return self::SUCCESS;
        }

        $products = $this->getProducts();

        if ($products->isEmpty()) {
            $this->warn('No products found.');

            return self::SUCCESS;
        }

        $this->displayProducts($products);

        return self::SUCCESS;
    }

    private function getProducts()
    {
        $query = Product::query();

        if ($nameHashes = $this->option('name-hashes')) {
            $hashes = array_map('trim', explode(',', $nameHashes));

            return $query->whereIn('name_hash', $hashes)->get();
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $limit = (int) $this->option('limit');

        return $query->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    private function displayProducts($products): void
    {
        $withExplanation = $this->option('with-explanation');

        if ($withExplanation) {
            $this->displayProductsWithExplanation($products);
        } else {
            $this->displayProductsTable($products);
        }
    }

    private function displayProductsTable($products): void
    {
        $headers = ['Name Hash', 'Name', 'Category', 'Is Food', 'Status', 'Created'];
        $rows    = [];

        foreach ($products as $product) {
            $status = $product->status ?: 'unclassified';

            // Normalize status for display
            $displayStatus = match (strtoupper((string) $status)) {
                'HIGH'     => 'HIGH',
                'LOW'      => 'LOW',
                'MODERATE' => 'MODERATE',
                'UNKNOWN'  => 'UNKNOWN',
                'PENDING'  => 'PENDING',
                'NA'       => 'NA',
                default    => strtoupper((string) $status),
            };

            $statusColor = $this->getStatusColor($displayStatus);
            $isFoodText  = $product->is_food  === null ? 'unknown' : ($product->is_food ? 'yes' : 'no');
            $isFoodColor = $product->is_food  === null ? 'yellow' : ($product->is_food ? 'green' : 'gray');

            $rows[] = [
                $product->name_hash,
                $this->truncate($product->name, 25),
                $this->truncate($product->category, 20),
                sprintf('<fg=%s>%s</>', $isFoodColor, $isFoodText),
                sprintf('<fg=%s>%s</>', $statusColor, $displayStatus),
                $product->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table($headers, $rows);
    }

    private function displayProductsWithExplanation($products): void
    {
        foreach ($products as $index => $product) {
            if ($index > 0) {
                $this->newLine();
            }

            $status        = $product->status ?: 'unclassified';
            $displayStatus = strtoupper((string) $status);
            $statusColor   = $this->getStatusColor($displayStatus);

            $isFoodText  = $product->is_food  === null ? 'Nepoznato' : ($product->is_food ? 'Da' : 'Ne');
            $isFoodColor = $product->is_food  === null ? 'yellow' : ($product->is_food ? 'green' : 'gray');

            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->line('📦 <fg=cyan>' . $product->name . '</>');
            $this->line('🆔 Name Hash: <fg=yellow>' . $product->name_hash . '</>');
            $this->line('📂 Kategorija: ' . $product->category);
            $this->line('🍽️  Hrana: <fg=' . $isFoodColor . '>' . $isFoodText . '</>');
            $this->line('⚡ FODMAP Status: <fg=' . $statusColor . '>' . $displayStatus . '</>');

            if ($product->explanation) {
                $this->line('💬 Objašnjenje: <fg=white>' . $product->explanation . '</>');
            } else {
                $this->line('💬 Objašnjenje: <fg=gray>Nema objašnjenja</>');
            }

            $this->line('📅 Kreiran: ' . $product->created_at->format('d.m.Y H:i'));

            if ($product->processed_at) {
                $this->line('✅ Obrađen: ' . $product->processed_at->format('d.m.Y H:i'));
            } else {
                $this->line('⏳ <fg=yellow>Čeka na obradu</>');
            }
        }

        if ($products->count() > 1) {
            $this->newLine();
            $this->info('Ukupno proizvoda: ' . $products->count());
        }
    }

    private function showStatistics(): void
    {
        $total        = Product::count();
        $statusCounts = Product::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray()
        ;

        $foodCounts = Product::selectRaw('is_food, COUNT(*) as count')
            ->whereNotNull('is_food')
            ->groupBy('is_food')
            ->pluck('count', 'is_food')
            ->toArray()
        ;

        $unclassified = Product::where(function ($query): void {
            $query->whereNull('status')
                ->orWhere('status', '')
                ->orWhere('status', 'PENDING')
                ->orWhere('status', 'UNKNOWN')
            ;
        })->count();

        $withExplanations = Product::whereNotNull('explanation')
            ->where('explanation', '!=', '')
            ->count()
        ;

        $this->info('FODMAP Classification Statistics');
        $this->line('================================');

        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['Total Products', $total, '100%'],
                ['High FODMAP', $statusCounts['HIGH'] ?? 0, $this->percentage($statusCounts['HIGH'] ?? 0, $total)],
                ['Low FODMAP', $statusCounts['LOW'] ?? 0, $this->percentage($statusCounts['LOW'] ?? 0, $total)],
                ['Moderate FODMAP', $statusCounts['MODERATE'] ?? 0, $this->percentage($statusCounts['MODERATE'] ?? 0, $total)],
                ['Unknown', ($statusCounts['UNKNOWN'] ?? 0) + ($statusCounts['unknown'] ?? 0), $this->percentage(($statusCounts['UNKNOWN'] ?? 0) + ($statusCounts['unknown'] ?? 0), $total)],
                ['Non-Food (NA)', $statusCounts['NA'] ?? 0, $this->percentage($statusCounts['NA'] ?? 0, $total)],
                ['Pending/Unclassified', $unclassified, $this->percentage($unclassified, $total)],
            ]
        );

        $this->newLine();
        $this->line('Food Classification');
        $this->line('==================');

        $this->table(
            ['Type', 'Count', 'Percentage'],
            [
                ['Food Items', $foodCounts[1] ?? 0, $this->percentage($foodCounts[1] ?? 0, $total)],
                ['Non-Food Items', $foodCounts[0] ?? 0, $this->percentage($foodCounts[0] ?? 0, $total)],
                ['With Explanations', $withExplanations, $this->percentage($withExplanations, $total)],
            ]
        );

        if ($unclassified > 0) {
            $this->newLine();
            $this->comment("💡 Run 'php artisan fodmap:classify --all' to classify unclassified products.");
        }
    }

    private function getStatusColor(string $status): string
    {
        return match (strtoupper($status)) {
            'HIGH'     => 'red',
            'LOW'      => 'green',
            'MODERATE' => 'yellow',
            'UNKNOWN'  => 'yellow',
            'PENDING'  => 'cyan',
            'NA'       => 'gray',
            default    => 'gray',
        };
    }

    private function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }

    private function percentage(int $count, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }

        return round(($count / $total) * 100, 1) . '%';
    }
}
