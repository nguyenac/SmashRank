<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillSnapshot extends Model
{
    protected $fillable = [
        'athlete_id', 'recorded_at', 'power', 'speed', 'defense', 'net_play', 'stamina',
    ];

    protected $casts = ['recorded_at' => 'date:Y-m-d'];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
