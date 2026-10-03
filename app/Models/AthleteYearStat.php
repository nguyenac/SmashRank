<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteYearStat extends Model
{
    protected $fillable = [
        'athlete_id', 'year', 'matches', 'wins', 'losses', 'finals', 'titles',
    ];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
