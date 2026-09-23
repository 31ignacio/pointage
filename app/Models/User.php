<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        "name",
        "email",
        "password",
        "role",
    ];

    protected $hidden = [
        "password",
        "remember_token",
    ];

    protected function casts(): array
    {
        return [
            "email_verified_at" => "datetime",
            "password" => "hashed",
        ];
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    // Roles : employe | responsable | rh_admin | super_admin
    public function isAdmin(): bool
    {
        return in_array($this->role, ["responsable", "rh_admin", "super_admin"], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === "super_admin";
    }
}
