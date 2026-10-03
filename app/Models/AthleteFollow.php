<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AthleteFollow extends Model
{
    protected $fillable = ['user_id', 'athlete_id'];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
