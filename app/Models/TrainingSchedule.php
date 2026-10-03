<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingSchedule extends Model
{
    protected $fillable = ['athlete_id', 'coach_id', 'week_start', 'day_of_week', 'drill', 'detail', 'done'];

    protected $casts = ['done' => 'boolean', 'week_start' => 'date:Y-m-d'];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
