<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attendance_day_id')
                ->constrained('attendance_days')
                ->cascadeOnDelete();

            $table->foreignId('requested_by') // 申請者（一般ユーザー）
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('reason')->nullable(); // 申請理由（任意）

            // 提案値（休憩は申請対象外）
            $table->dateTime('proposed_clock_in_at')->nullable();
            $table->dateTime('proposed_clock_out_at')->nullable();
            $table->string('proposed_note', 255)->nullable();

            // 現在の状態（ログは別テーブルに積む）
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();
            $table->index(['attendance_day_id', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
    }
};
