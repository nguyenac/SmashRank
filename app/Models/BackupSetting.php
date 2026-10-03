<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    protected $fillable = ['frequency', 'enabled', 'drive_folder_id', 'last_run_at'];

    protected $casts = ['enabled' => 'boolean', 'last_run_at' => 'datetime'];

    public static function current(): self
    {
        return static::first() ?? static::create(['frequency' => 'daily', 'enabled' => true]);
    }
}
