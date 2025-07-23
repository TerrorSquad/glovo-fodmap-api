<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'category',
        'is_food',
        'status',
        'explanation',
        'processed_at',
        'name_hash',
    ];

    protected $casts = [
        'id'           => 'integer',
        'is_food'      => 'boolean',
        'processed_at' => 'datetime',
        'name_hash'    => 'string',
    ];
}
