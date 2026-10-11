<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        "user_id",
        "matricule",
        "first_name",
        "last_name",
        "phone",
        "department_id",
        "site_id",
        "status",
        "device_fallback_enabled",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function device()
    {
        return $this->hasOne(Device::class);
    }

    public function deviceChangeRequests()
    {
        return $this->hasMany(DeviceChangeRequest::class);
    }

    protected function casts(): array
    {
        return ['device_fallback_enabled' => 'boolean'];
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Dernier pointage (arrivée ou sortie), tous jours confondus
    public function lastRecord()
    {
        return $this->attendanceRecords()
            ->where("status", "accepted")
            ->latest("recorded_at")
            ->first();
    }

    // Dernier pointage du jour en cours
    public function lastRecordToday()
    {
        return $this->attendanceRecords()
            ->where("status", "accepted")
            ->whereDate("recorded_at", now()->toDateString())
            ->latest("recorded_at")
            ->first();
    }
}
