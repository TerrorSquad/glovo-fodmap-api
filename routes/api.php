<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DocsController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

// System endpoints
Route::get('/health', [DocsController::class, 'health']);

// Documentation endpoints
Route::get('/docs/openapi.json', [DocsController::class, 'openApiJson']);
Route::get('/docs/openapi.yaml', [DocsController::class, 'openApiYaml']);

// Product classification endpoints
Route::post('/v1/products/submit', [ProductController::class, 'submit']);
Route::post('/v1/products/status', [ProductController::class, 'status']);
