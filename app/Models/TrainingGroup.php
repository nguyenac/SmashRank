<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingGroup extends Model
{
    protected $fillable = ['name', 'coach_id', 'creator_id', 'description'];

    public function members(): HasMany
    {
        return $this->hasMany(TrainingGroupMember::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
