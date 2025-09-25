<?php

namespace Database\Factories;

use App\Models\BreakPeriod;
use App\Models\AttendanceDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BreakPeriodFactory extends Factory
{
    protected $model = BreakPeriod::class;

    public function definition(): array
    {
        // attendance_day が先に存在している前提で時間帯を決める
        return [
            'attendance_day_id' => AttendanceDay::factory(),
            'started_at' => function (array $attrs) {
                $day = AttendanceDay::find($attrs['attendance_day_id']);
                $base = $day?->clock_in_at ?? Carbon::parse($day->work_date . ' 12:00');
                return (clone $base)->addHours(3)->addMinutes(rand(0, 30));
            },
            'ended_at' => function (array $attrs) {
                $day = AttendanceDay::find($attrs['attendance_day_id']);
                $base = $day?->clock_in_at ?? Carbon::parse($day->work_date . ' 12:00');
                return (clone $base)->addHours(4)->addMinutes(rand(0, 30));
            },
        ];
    }
}
