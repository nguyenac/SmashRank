<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $fillable = ['filename', 'size_kb', 'drive_file_id', 'status', 'type'];
}

