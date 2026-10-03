<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankingHistory extends Model
{
    protected $fillable = [
        'athlete_id', 'recorded_month', 'points', 'elo_rating', 'win_rate',
    ];

    protected $casts = [
        'recorded_month' => 'date:Y-m-d',
    ];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
