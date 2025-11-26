<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('correction_requests', 'payload')) {
            Schema::table('correction_requests', function (Blueprint $table) {
                $table->json('payload')->nullable()->after('status');
            });
        }
    }
    public function down(): void
    {
        if (Schema::hasColumn('correction_requests', 'payload')) {
            Schema::table('correction_requests', function (Blueprint $table) {
                $table->dropColumn('payload');
            });
        }
    }
};
