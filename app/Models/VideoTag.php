<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoTag extends Model
{
    protected $fillable = ['athlete_id', 'user_id', 'title', 'timestamp_ms', 'file_path'];

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(Athlete::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
