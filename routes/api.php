<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/classify', [ProductController::class, 'classify']);
