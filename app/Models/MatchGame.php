<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchGame extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'tournament_id', 'category', 'athlete1_id', 'athlete2_id',
        'score1', 'score2', 'walkover', 'rating_change', 'played_at',
        'venue', 'result_tags', 'result_note',
        'match_code', 'status', 'created_by_user_id', 'confirmed_by_user_id',
    ];

    protected $casts = [
        'walkover' => 'boolean',
        'played_at' => 'date:Y-m-d',
        'result_tags' => 'array',
    ];

    public function athlete1(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'athlete1_id');
    }

    public function athlete2(): BelongsTo
    {
        return $this->belongsTo(Athlete::class, 'athlete2_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function winnerId(): int
    {
        return $this->score1 > $this->score2 ? $this->athlete1_id : $this->athlete2_id;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
