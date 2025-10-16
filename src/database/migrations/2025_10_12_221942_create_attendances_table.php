<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date')->index();              // 勤務日
            $table->dateTime('clock_in_at')->nullable();     // 出勤
            $table->dateTime('clock_out_at')->nullable();    // 退勤
            $table->unsignedSmallInteger('break_minutes')->default(0);
            // 0:勤務外,1:出勤中,2:休憩中,3:退勤済 など 好きに
            $table->unsignedTinyInteger('status')->default(0)->index();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']); // 1ユーザー1日1レコードにしたい場合
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendances');
    }
}
