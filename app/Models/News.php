<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class News extends Model
{
    protected $fillable = ['title', 'body', 'source', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function comments(): HasMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
