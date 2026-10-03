<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlLog extends Model
{
    protected $fillable = [
        'target', 'source', 'items_found', 'items_upserted', 'status', 'message',
    ];
}
