<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unique('device_id');
        });

        Schema::table('device_change_requests', function (Blueprint $table) {
            $table->string('new_device_user_agent', 500)->nullable();
            $table->string('new_device_ip', 45)->nullable();
            $table->timestamp('employee_notified_at')->nullable();
        });

        Schema::create('device_id_owners', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 191)->unique();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        foreach (DB::table('devices')->get(['device_id', 'employee_id']) as $device) {
            DB::table('device_id_owners')->insert([
                'device_id' => $device->device_id,
                'employee_id' => $device->employee_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_id_owners');
        Schema::table('device_change_requests', function (Blueprint $table) {
            $table->dropColumn(['new_device_user_agent', 'new_device_ip', 'employee_notified_at']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['device_id']);
        });
    }
};
