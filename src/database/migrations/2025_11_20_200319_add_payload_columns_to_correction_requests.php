<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('correction_requests', function (Blueprint $table) {
            // すでにあればスキップでOK
            $table->json('before_payload')->nullable()->after('status');
            $table->json('after_payload')->nullable()->after('before_payload');
        });
    }

    public function down()
    {
        Schema::table('correction_requests', function (Blueprint $table) {
            $table->dropColumn(['before_payload', 'after_payload']);
        });
    }
};
