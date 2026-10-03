<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteDetail extends Model
{
    public $timestamps = true;

    protected $primaryKey = 'athlete_id';
    public $incrementing = false;

    protected $fillable = [
        'athlete_id', 'birth_date', 'height_cm', 'weight_kg', 'playing_style',
        'coach', 'association', 'team', 'titles', 'finals', 'total_matches',
        'total_wins', 'win_streak_current', 'win_streak_career',
        'win_streak_career_excl_wo', 'super_streak', 'not_played_matches',
        'goat_points', 'source', 'source_id', 'playstyle_scores', 'zone_marks',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'playstyle_scores' => 'array',
        'zone_marks' => 'array',
    ];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }
}
