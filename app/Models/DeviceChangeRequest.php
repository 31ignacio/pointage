<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceChangeRequest extends Model
{
    protected $fillable = [
        'employee_id', 'old_device_id', 'new_device_id', 'new_device_user_agent', 'new_device_ip', 'status', 'reviewed_by', 'reviewed_at', 'employee_notified_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'employee_notified_at' => 'datetime'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
