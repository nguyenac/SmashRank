<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingGoal extends Model
{
    protected $fillable = [
        'user_id', 'title', 'target', 'drill', 'frequency',
        'deadline', 'status', 'progress', 'coach_advice',
    ];

    protected $casts = ['deadline' => 'date:Y-m-d'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
