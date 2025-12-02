<?php

namespace Database\Factories;

use App\Models\BreakPeriod;
use App\Models\AttendanceDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

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
     * ※ state 時点では related の work_date が取れないことがあるため、
     *   ここでは「Y-m-d が分かるならそれに寄せる／無ければ今日」に揃える。
     */
    public function between(string $startHm, string $endHm): self
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        return $this->state(function (array $attr) use ($startHm, $endHm, $tz) {
            // 可能なら attr 経由の work_date を拾う（無いことが多い）
            $ymd = isset($attr['work_date'])
                ? $this->toYmd($attr['work_date'], $tz)
                : Carbon::now($tz)->toDateString();

            $base = Carbon::createFromFormat('Y-m-d', $ymd, $tz);

            return [
                'started_at' => (clone $base)->setTimeFromTimeString($startHm),
                'ended_at'   => (clone $base)->setTimeFromTimeString($endHm),
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
     */
    public function configure(): self
    {
        return $this->afterCreating(function (BreakPeriod $bp) {
            $tz  = config('app.timezone', 'Asia/Tokyo');

            /** @var AttendanceDay|null $day */
            $day = $bp->attendanceDay()->first();
            if (!$day) return;

            // 1) 既に両方入っている → 触らない
            if ($bp->started_at && $bp->ended_at) return;

            // 2) started_at がある & ended_at が無い → 「休憩中」を壊さない（何もしない）
            if ($bp->started_at && !$bp->ended_at) return;

            // 3) 両方 null のときのみ、自動補完（12:00-13:00 か clock_in_at +3h）
            if (!$bp->started_at && !$bp->ended_at) {
                // ベース日付
                $ymd  = $day->work_date instanceof \Carbon\Carbon
                    ? $day->work_date->copy()->setTimezone($tz)->toDateString()
                    : \Carbon\Carbon::parse($day->work_date, $tz)->toDateString();

                $base  = \Carbon\Carbon::createFromFormat('Y-m-d', $ymd, $tz);
                $start = (clone $base)->setTimeFromTimeString('12:00');
                $end   = (clone $base)->setTimeFromTimeString('13:00');

                // clock_in_at があるなら 3h 後〜1h の軽微調整
                if ($day->clock_in_at) {
                    $cin   = $day->clock_in_at instanceof \Carbon\Carbon
                        ? $day->clock_in_at->copy()->setTimezone($tz)
                        : \Carbon\Carbon::parse($day->clock_in_at, $tz);
                    $start = $cin->copy()->addHours(3);
                    $end   = $start->copy()->addHour();
                }

                $bp->update([
                    'started_at' => $start,
                    'ended_at'   => $end,
                ]);
            }
        });
    }


    /**
     * 与えられた日付/日時から Y-m-d を返す（Carbon|string どちらにも対応）
     */
    private function toYmd($date, string $tz): string
    {
        if ($date instanceof Carbon) {
            return $date->copy()->setTimezone($tz)->toDateString();
        }
        return Carbon::parse($date, $tz)->toDateString();
    }
}
