<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtUsage extends Model
{
    protected $fillable = ['court_name', 'usage_date', 'hours', 'bookings'];

    protected $casts = ['usage_date' => 'date:Y-m-d'];
}
