<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceScheduleSetting extends Model
{
    protected $fillable = ['arrival_time', 'departure_time', 'updated_by'];
}
