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
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $date = Carbon::now($tz)->subDays(rand(0, 20))->toDateString();

        // 85% の確率で打刻あり
        $hasClock = $this->faker->boolean(85);

        // 8:00〜10:00 で出勤、17:00〜20:00 で退勤（たまにどちらか欠損）
        $clockIn  = $hasClock ? Carbon::parse("$date 09:00", $tz)->subMinutes(rand(0, 60)) : null;
        $clockOut = $hasClock ? Carbon::parse("$date 18:00", $tz)->addMinutes(rand(0, 120)) : null;

        // status 推定（実装の enum/値に合わせて後から state で上書き可能）
        $status = match (true) {
            !$hasClock              => 'before', // 未出勤
            $this->faker->boolean() => 'after',  // 退勤済
            default                 => 'working' // 出勤中
        };

        return [
            'user_id'             => User::factory(),
            'work_date'           => $date,
            'clock_in_at'         => $clockIn,
            'clock_out_at'        => $clockOut,
            'status'              => $status,
            'note'                => $this->faker->boolean(20) ? $this->faker->realText(20) : null,
            'total_work_minutes'  => null, // コントローラ or mutator で計算する前提
            'total_break_minutes' => null,
        ];
    }

    /** 対象日固定 */
    public function onDate(string $ymd): self
    {
        return $this->state(fn() => ['work_date' => $ymd]);
    }

    /** 出勤・退勤を「HH:MM」文字列で確定（テストの再現性向上） */
    public function clockInOut(?string $in, ?string $out): self
    {
        $tz = config('app.timezone', 'Asia/Tokyo');
        return $this->state(function (array $attr) use ($in, $out, $tz) {
            $date = $attr['work_date'] ?? Carbon::now($tz)->toDateString();
            return [
                'clock_in_at'  => $in  ? Carbon::parse("$date $in",  $tz) : null,
                'clock_out_at' => $out ? Carbon::parse("$date $out", $tz) : null,
            ];
        });
    }

    /** 退勤なし（勤務中想定） */
    public function withoutClockOut(): self
    {
        return $this->state(fn() => ['clock_out_at' => null, 'status' => 'working']);
    }

    public function before(): self
    {
        return $this->state(fn() => ['status' => 'before', 'clock_in_at' => null, 'clock_out_at' => null]);
    }
    public function working(): self
    {
        return $this->state(fn() => ['status' => 'working']);
    }
    public function break(): self
    {
        return $this->state(fn() => ['status' => 'break']);
    }
    public function after(): self
    {
        return $this->state(fn() => ['status' => 'after']);
    }

    public function withNote(string $note): self
    {
        return $this->state(fn() => ['note' => $note]);
    }

    /** 特定ユーザーで作成 */
    public function forUser(User $user): self
    {
        return $this->state(fn() => ['user_id' => $user->id]);
    }
}
