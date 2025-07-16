<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

// Product classification endpoints
Route::post('/v1/products/submit', [ProductController::class, 'submit']);
Route::post('/v1/products/status', [ProductController::class, 'status']);
