<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Injury extends Model
{
    protected $fillable = ['athlete_id', 'type', 'description', 'occurred_at', 'expected_recovery', 'status'];

    protected $casts = ['occurred_at' => 'date:Y-m-d', 'expected_recovery' => 'date:Y-m-d'];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
