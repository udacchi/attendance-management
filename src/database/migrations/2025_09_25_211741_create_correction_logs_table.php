<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('correction_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('correction_request_id')
                ->constrained('correction_requests')
                ->cascadeOnDelete();

            $table->foreignId('admin_id') // 実行者：管理者
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('action', ['approved', 'rejected'])->index();
            $table->text('comment')->nullable(); // 理由・メモ
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('correction_logs');
    }
};
