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
                            {--external-id= : Show status for a specific external ID}
                            {--status= : Filter by status (low, high, unknown)}
                            {--limit=10 : Number of products to show (default: 10)}
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

        if ($externalId = $this->option('external-id')) {
            return $query->where('external_id', $externalId)->get();
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $limit = (int) $this->option('limit');

        return $query->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    private function displayProducts($products): void
    {
        $headers = ['External ID', 'Name', 'Category', 'Status', 'Created'];
        $rows    = [];

        foreach ($products as $product) {
            $status = $product->status ?: 'unclassified';

            // Normalize status for display
            $displayStatus = match (strtoupper((string) $status)) {
                'HIGH'    => 'high',
                'LOW'     => 'low',
                'UNKNOWN' => 'unknown',
                'PENDING' => 'pending',
                'NA'      => 'na',
                default   => strtolower((string) $status),
            };

            $statusColor = $this->getStatusColor($displayStatus);

            $rows[] = [
                $product->external_id,
                $this->truncate($product->name, 30),
                $this->truncate($product->category, 20),
                sprintf('<fg=%s>%s</>', $statusColor, $displayStatus),
                $product->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table($headers, $rows);
    }

    private function showStatistics(): void
    {
        $total        = Product::count();
        $statusCounts = Product::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray()
        ;

        $unclassified = Product::whereIn('status', ['PENDING', 'UNKNOWN'])
            ->orWhereNull('status')
            ->orWhere('status', '')
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
                ['Unknown', ($statusCounts['UNKNOWN'] ?? 0) + ($statusCounts['unknown'] ?? 0), $this->percentage(($statusCounts['UNKNOWN'] ?? 0) + ($statusCounts['unknown'] ?? 0), $total)],
                ['Non-Food (NA)', $statusCounts['NA'] ?? 0, $this->percentage($statusCounts['NA'] ?? 0, $total)],
                ['Pending/Unclassified', ($statusCounts['PENDING'] ?? 0) + $unclassified, $this->percentage(($statusCounts['PENDING'] ?? 0) + $unclassified, $total)],
            ]
        );

        if ($unclassified > 0) {
            $this->newLine();
            $this->comment("💡 Run 'php artisan fodmap:classify --all' to classify unclassified products.");
        }
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'high'    => 'red',
            'low'     => 'green',
            'unknown' => 'yellow',
            'pending' => 'cyan',
            'na'      => 'gray',
            default   => 'gray',
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
