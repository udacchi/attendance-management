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
            'action'   => $this->faker->randomElement(['approved', 'rejected']),
            'comment'  => $this->faker->boolean(50) ? $this->faker->realText(20) : null,
        ];
    }

    public function approved(): self
    {
        return $this->state(fn() => ['action' => 'approved']);
    }

    public function rejected(): self
    {
        return $this->state(fn() => ['action' => 'rejected']);
    }

    public function forRequest(CorrectionRequest $req): self
    {
        return $this->state(fn() => ['correction_request_id' => $req->id]);
    }

    public function byAdmin(User $admin): self
    {
        return $this->state(fn() => ['admin_id' => $admin->id]);
    }
}
