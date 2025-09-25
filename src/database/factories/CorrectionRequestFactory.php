<?php

namespace Database\Factories;

use App\Models\CorrectionRequest;
use App\Models\AttendanceDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CorrectionRequestFactory extends Factory
{
    protected $model = CorrectionRequest::class;

    public function definition(): array
    {
        return [
            // ここはこのままでOK（明示的に上書きすればそちらが優先される）
            'attendance_day_id' => AttendanceDay::factory(),
            'requested_by'      => User::factory(),
            'reason'            => $this->faker->boolean(70) ? $this->faker->realText(30) : null,
            'proposed_clock_in_at'  => null,
            'proposed_clock_out_at' => null,
            'proposed_note'     => $this->faker->boolean(30) ? $this->faker->realText(20) : null,
            'status'            => CorrectionRequest::STATUS_PENDING,
        ];
    }

    /**
     * 作成後に、関連する AttendanceDay の打刻から提案値を埋める
     */
    public function withProposedTimes(): self
    {
        return $this->afterCreating(function (CorrectionRequest $req) {
            $day = $req->attendanceDay; // リレーション経由でOK

            if (!$day || !$day->clock_in_at || !$day->clock_out_at) {
                return; // 片方無い日は何もしない
            }

            $in  = (clone $day->clock_in_at)->subMinutes(random_int(5, 15));
            $out = (clone $day->clock_out_at)->addMinutes(random_int(5, 15));

            // 提案値を更新
            $req->update([
                'proposed_clock_in_at'  => $in,
                'proposed_clock_out_at' => $out,
            ]);
        });
    }
}
