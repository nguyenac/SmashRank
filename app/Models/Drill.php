<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Drill extends Model
{
    protected $fillable = [
        'creator_id', 'title', 'detail', 'category', 'difficulty',
        'duration_min', 'level_hint', 'is_reference',
    ];

    protected $casts = ['is_reference' => 'boolean'];
}
