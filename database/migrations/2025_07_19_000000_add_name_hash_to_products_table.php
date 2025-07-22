<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add name_hash column to products table for deduplication.
 */
class AddNameHashToProductsTable extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('name_hash', 64)->unique()->nullable()->after('name');
            $table->index('name_hash');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['name_hash']);
            $table->dropColumn('name_hash');
        });
    }
}
