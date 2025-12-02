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
        // デフォルトは null（あとで afterCreating で補完）
        return [
            'attendance_day_id' => AttendanceDay::factory(),
            'started_at'        => null,
            'ended_at'          => null,
        ];
    }

    /**
     * 指定の HH:MM 間で作成（work_date に合わせる）
     */
    public function between(string $start, string $end): self
    {
        $tz = config('app.timezone', 'Asia/Tokyo');
        return $this->state(function (array $attr) use ($start, $end, $tz) {
            // work_date は afterCreating の方が確実だが、stateでもできるだけ寄せる
            $ymd = $attr['work_date'] ?? Carbon::now($tz)->toDateString();
            return [
                'started_at' => Carbon::parse("$ymd $start", $tz),
                'ended_at'   => Carbon::parse("$ymd $end",   $tz),
            ];
        });
    }

    /**
     * start/end を直接指定（work_date に依存しない）
     */
    public function startEnd(string $startDateTime, string $endDateTime): self
    {
        $tz = config('app.timezone', 'Asia/Tokyo');
        return $this->state(fn() => [
            'started_at' => Carbon::parse($startDateTime, $tz),
            'ended_at'   => Carbon::parse($endDateTime,   $tz),
        ]);
    }

    /**
     * attendance_day に基づき、未指定なら 12:00〜13:00 を自動補完
     * （DB検索はここで行う：ファクトリアトリビュート内では行わない）
     */
    public function configure(): self
    {
        return $this->afterCreating(function (BreakPeriod $bp) {
            $tz  = config('app.timezone', 'Asia/Tokyo');
            $day = $bp->attendanceDay()->first(); // リレーションから取得

            if (!$day) return;

            $date = $day->work_date;
            // 既に start/end が入っていれば触らない
            if ($bp->started_at && $bp->ended_at) return;

            $start = Carbon::parse("$date 12:00", $tz);
            $end   = Carbon::parse("$date 13:00", $tz);

            // clock_in があるなら少し後ろ倒しにする軽微なロジック
            if ($day->clock_in_at) {
                $start = $day->clock_in_at->copy()->addHours(3);
                $end   = $start->copy()->addHour();
            }

            $bp->update([
                'started_at' => $bp->started_at ?: $start,
                'ended_at'   => $bp->ended_at   ?: $end,
            ]);
        });
    }
}
