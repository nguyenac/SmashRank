<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Brand extends Model
{
    protected $fillable = [
        'name', 'slug', 'country', 'logo_url', 'description', 'source', 'source_id',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(EquipmentItem::class);
    }

    public function rackets(): HasMany
    {
        return $this->hasMany(EquipmentItem::class)->where('type', 'racket');
    }

    public function shoes(): HasMany
    {
        return $this->hasMany(EquipmentItem::class)->where('type', 'shoes');
    }

    /** Tất cả VĐV sử dụng sản phẩm của thương hiệu này (qua vợt hoặc giày). */
    public function athletesUsingRackets(): HasManyThrough
    {
        return $this->hasManyThrough(
            Athlete::class, EquipmentItem::class,
            'brand_id', 'racket_id'
        );
    }
}
