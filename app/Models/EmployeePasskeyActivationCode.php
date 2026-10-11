<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePasskeyActivationCode extends Model
{
    protected $fillable = ['employee_id', 'code_hash', 'generated_by', 'expires_at', 'used_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
