<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('break_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_day_id')
                ->constrained('attendance_days')
                ->cascadeOnDelete();

            $table->dateTime('started_at');           // 休憩入
            $table->dateTime('ended_at')->nullable(); // 休憩戻（未戻りならNULL）

            $table->timestamps();
            $table->index(['attendance_day_id', 'started_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('break_periods');
    }
};
