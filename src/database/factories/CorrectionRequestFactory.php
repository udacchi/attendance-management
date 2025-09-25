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
            'attendance_day_id' => AttendanceDay::factory(),
            'requested_by' => User::factory(),
            'reason' => $this->faker->boolean(70) ? $this->faker->realText(30) : null,
            'proposed_clock_in_at' => null,
            'proposed_clock_out_at' => null,
            'proposed_note' => $this->faker->boolean(30) ? $this->faker->realText(20) : null,
            'status' => CorrectionRequest::STATUS_PENDING,
        ];
    }

    public function withProposedTimes(): self
    {
        return $this->state(function (array $attrs) {
            $dayId = $attrs['attendance_day_id'] ?? null;
            $day   = $dayId ? AttendanceDay::find($dayId) : null;

            $in  = optional($day?->clock_in_at)->subMinutes(rand(5, 15));
            $out = optional($day?->clock_out_at)->addMinutes(rand(5, 15));

            return [
                'proposed_clock_in_at' => $in,
                'proposed_clock_out_at' => $out,
            ];
        });
    }
}
