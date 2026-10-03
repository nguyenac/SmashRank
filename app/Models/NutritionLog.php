<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionLog extends Model
{
    protected $fillable = [
        'athlete_id', 'log_date', 'meal', 'description',
        'calories', 'protein_g', 'carbs_g', 'water_ml',
    ];

    protected $casts = ['log_date' => 'date:Y-m-d'];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
