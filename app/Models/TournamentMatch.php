<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentMatch extends Model
{
    protected $fillable = [
        'tournament_id', 'round', 'slot', 'athlete1_id', 'athlete2_id',
        'score1', 'score2', 'status', 'starts_at',
    ];

    protected $casts = ['starts_at' => 'datetime'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function athlete1(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'athlete1_id');
    }

    public function athlete2(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'athlete2_id');
    }
}
