<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentItem extends Model
{
    protected $fillable = [
        'name', 'type', 'brand', 'brand_id', 'model', 'description',
        'price', 'image_url', 'specifications', 'source', 'source_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'specifications' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function athletesUsingRacket(): HasMany
    {
        return $this->hasMany(Athlete::class, 'racket_id');
    }

    public function athletesUsingShoes(): HasMany
    {
        return $this->hasMany(Athlete::class, 'shoes_id');
    }
}
