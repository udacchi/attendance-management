<?php

namespace Database\Factories;

use App\Models\CorrectionRequest;
use App\Models\AttendanceDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class CorrectionRequestFactory extends Factory
{
    protected $model = CorrectionRequest::class;

    public function definition(): array
    {
        return [
            'attendance_day_id'     => AttendanceDay::factory(),
            'requested_by'          => User::factory(),
            'reason'                => $this->faker->boolean(70) ? $this->faker->realText(30) : null,
            'proposed_clock_in_at'  => null,
            'proposed_clock_out_at' => null,
            'proposed_note'         => $this->faker->boolean(30) ? $this->faker->realText(20) : null,
            'status'                => 'pending', // 定数があればそれに置換
        ];
    }

    public function pending(): self
    {
        return $this->state(fn() => ['status' => 'pending']);
    }
    public function approved(): self
    {
        return $this->state(fn() => ['status' => 'approved']);
    }
    public function rejected(): self
    {
        return $this->state(fn() => ['status' => 'rejected']);
    }

    /** 対象日の提案値を自動生成（勤怠の+/-微調整）*/
    public function withProposedTimes(): self
    {
        return $this->afterCreating(function (CorrectionRequest $req) {
            $day = $req->attendanceDay;
            if (!$day) return;

            $tz = config('app.timezone', 'Asia/Tokyo');

            $in  = $day->clock_in_at  ? $day->clock_in_at->copy()->addMinutes(10) : null;
            $out = $day->clock_out_at ? $day->clock_out_at->copy()->subMinutes(10) : null;

            if ($in || $out) {
                $req->update([
                    'proposed_clock_in_at'  => $in,
                    'proposed_clock_out_at' => $out,
                ]);
            }
        });
    }

    /** 直接提案値を指定（テストでピンポイントに）*/
    public function withProposed(string $date, ?string $inHHmm, ?string $outHHmm): self
    {
        $tz = config('app.timezone', 'Asia/Tokyo');
        return $this->state(fn() => [
            'proposed_clock_in_at'  => $inHHmm  ? Carbon::parse("$date $inHHmm",  $tz) : null,
            'proposed_clock_out_at' => $outHHmm ? Carbon::parse("$date $outHHmm", $tz) : null,
        ]);
    }

    public function forDay(AttendanceDay $day): self
    {
        return $this->state(fn() => ['attendance_day_id' => $day->id]);
    }

    public function byUser(User $user): self
    {
        return $this->state(fn() => ['requested_by' => $user->id]);
    }
}
