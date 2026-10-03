<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentWinner extends Model
{
    protected $fillable = [
        'tournament_id', 'athlete_id', 'category', 'placement', 'achieved_at',
    ];

    protected $casts = ['achieved_at' => 'date:Y-m-d'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
