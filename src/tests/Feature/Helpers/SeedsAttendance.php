<?php

namespace Tests\Helpers;

use App\Models\AttendanceDay;
use App\Models\BreakPeriod;
use Carbon\Carbon;

trait SeedsAttendance
{
    protected function seedAttendance($user, string $date, ?string $in, ?string $out, array $breaks = []): AttendanceDay
    {
        $tz = config('app.timezone', 'Asia/Tokyo');
        $base = Carbon::parse($date, $tz);

        $day = AttendanceDay::factory()->create([
            'user_id' => $user->id,
            'work_date' => $base->toDateString(),
            'clock_in_at'  => $in  ? Carbon::parse("{$date} {$in}",  $tz) : null,
            'clock_out_at' => $out ? Carbon::parse("{$date} {$out}", $tz) : null,
        ]);

        foreach ($breaks as $b) {
            BreakPeriod::factory()->create([
                'attendance_day_id' => $day->id,
                'started_at' => $b['start'] ? Carbon::parse("{$date} {$b['start']}", $tz) : null,
                'ended_at'   => $b['end']   ? Carbon::parse("{$date} {$b['end']}",   $tz) : null,
            ]);
        }
        return $day;
    }
}
