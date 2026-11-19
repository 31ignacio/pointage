<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_schedule_settings', function (Blueprint $table) {
            $table->id();
            $table->time('arrival_time')->default('08:00:00');
            $table->time('departure_time')->default('19:30:00');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_schedule_settings');
    }
};
