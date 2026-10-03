<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingGroupMember extends Model
{
    protected $fillable = ['group_id', 'user_id', 'athlete_id'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(TrainingGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }
}
