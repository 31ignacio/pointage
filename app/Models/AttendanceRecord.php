<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        "employee_id",
        "site_id",
        "type",
        "recorded_at",
        "latitude",
        "longitude",
        "distance_meters",
        "device_id",
        "status",
        "rejection_reason",
    ];

    protected function casts(): array
    {
        return [
            "recorded_at" => "datetime",
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
