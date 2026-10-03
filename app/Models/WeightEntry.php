<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightEntry extends Model
{
    protected $fillable = ['athlete_id', 'measured_at', 'weight_kg', 'heart_rate'];

    protected $casts = ['measured_at' => 'date:Y-m-d'];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
