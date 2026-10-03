<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = [
        'name', 'level', 'is_asian_games', 'is_team_event', 'has_live_scores',
        'association', 'host_country', 'start_date', 'end_date', 'source', 'source_id',
    ];

    protected $casts = [
        'is_asian_games' => 'boolean',
        'is_team_event' => 'boolean',
        'has_live_scores' => 'boolean',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function winners(): HasMany
    {
        return $this->hasMany(TournamentWinner::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('round')->orderBy('slot');
    }
}
