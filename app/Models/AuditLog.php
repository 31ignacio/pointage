<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        "user_id",
        "employee_id",
        "action",
        "details",
        "ip_address",
        "created_at",
    ];

    protected function casts(): array
    {
        return [
            "created_at" => "datetime",
        ];
    }

    public static function log(string $action, ?string $details = null, ?int $employeeId = null): void
    {
        static::create([
            "user_id" => auth()->id(),
            "employee_id" => $employeeId,
            "action" => $action,
            "details" => $details,
            "ip_address" => request()?->ip(),
            "created_at" => now(),
        ]);
    }
}
