<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtpCode extends Model
{
    protected $fillable = ['email', 'code', 'expires_at', 'consumed'];

    protected $casts = ['expires_at' => 'datetime', 'consumed' => 'boolean'];
}
