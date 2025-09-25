<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('work_date'); // ユーザー×日で一意
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();

            // UIバッジ用（勤務外/出勤中/休憩中/退勤済）
            $table->enum('status', ['before', 'working', 'break', 'after'])->default('before');

            // 集計（分）…アプリ側で更新。NULL=未確定
            $table->unsignedInteger('total_work_minutes')->nullable();
            $table->unsignedInteger('total_break_minutes')->nullable();

            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index('work_date'); // 月次/日次検索向け
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('attendance_days');
    }
};
