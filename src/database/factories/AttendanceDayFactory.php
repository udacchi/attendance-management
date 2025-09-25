<?php

namespace Database\Factories;

use App\Models\AttendanceDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttendanceDayFactory extends Factory
{
    protected $model = AttendanceDay::class;

    public function definition(): array
    {
        $date = Carbon::today()->subDays(rand(0, 20))->toDateString();

        // 8:00〜10:00のどこかで出勤、17:00〜20:00で退勤（たまに未打刻）
        $hasClock = $this->faker->boolean(85);

        $clockIn  = $hasClock ? Carbon::parse($date . ' ' . $this->faker->time('H:i', '10:00'))->subMinutes(rand(0, 60)) : null;
        $clockOut = $hasClock ? Carbon::parse($date . ' ' . $this->faker->time('H:i', '20:00'))->subMinutes(rand(0, 60)) : null;

        // ステータス（ざっくり）
        $status = $hasClock
            ? ($this->faker->boolean(10) ? 'break' : 'after')
            : 'before';

        return [
            'user_id' => User::factory(),
            'work_date' => $date,
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'status' => $status,
            'note' => $this->faker->boolean(20) ? $this->faker->realText(20) : null,
            'total_work_minutes' => null,
            'total_break_minutes' => null,
        ];
    }
}
