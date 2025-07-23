<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if products table exists before adding indexes
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            // Performance indexes (only add if they don't exist)
            try {
                $table->index(['status', 'processed_at'], 'products_status_processed_idx');
            } catch (Exception $exception) {
                // Index might already exist, ignore
            }

            try {
                $table->index('processed_at', 'products_processed_at_idx');
            } catch (Exception $exception) {
                // Index might already exist, ignore
            }

            // Search optimization
            try {
                $table->index('name', 'products_name_idx');
            } catch (Exception) {
                // Index might already exist, ignore
            }

            try {
                $table->index(['name', 'category'], 'products_name_category_idx');
            } catch (Exception) {
                // Index might already exist, ignore
            }

            // Queue processing optimization
            try {
                $table->index(['status', 'created_at'], 'products_queue_processing_idx');
            } catch (Exception) {
                // Index might already exist, ignore
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            try {
                $table->dropIndex('products_status_processed_idx');
            } catch (Exception $exception) {
                // Index might not exist, ignore
            }

            try {
                $table->dropIndex('products_processed_at_idx');
            } catch (Exception $exception) {
                // Index might not exist, ignore
            }

            try {
                $table->dropIndex('products_name_idx');
            } catch (Exception) {
                // Index might not exist, ignore
            }

            try {
                $table->dropIndex('products_name_category_idx');
            } catch (Exception) {
                // Index might not exist, ignore
            }

            try {
                $table->dropIndex('products_queue_processing_idx');
            } catch (Exception) {
                // Index might not exist, ignore
            }
        });
    }
};
