<?php

namespace Database\Factories;

use App\Models\CorrectionLog;
use App\Models\CorrectionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CorrectionLogFactory extends Factory
{
    protected $model = CorrectionLog::class;

    public function definition(): array
    {
        return [
            'correction_request_id' => CorrectionRequest::factory(),
            'admin_id' => User::factory()->admin(),
            'action' => $this->faker->randomElement(['approved', 'rejected']),
            'comment' => $this->faker->boolean(50) ? $this->faker->realText(20) : null,
        ];
    }
}
