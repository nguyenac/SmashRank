<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSale extends Model
{
    protected $fillable = ['equipment_item_id', 'qty', 'total_price', 'sold_at'];

    protected $casts = ['sold_at' => 'date:Y-m-d'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(EquipmentItem::class, 'equipment_item_id');
    }
}
