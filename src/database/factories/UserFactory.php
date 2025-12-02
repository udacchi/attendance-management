<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            // PHPUnit 側で BCRYPT_ROUNDS=4 が効くのでそのまま bcrypt でOK
            'password' => bcrypt('password123'),
            'role' => 'user',
            'remember_token' => Str::random(10),
        ];
    }

    /** 管理者ロール */
    public function admin(): self
    {
        return $this->state(fn() => [
            'role'  => 'admin',
            'email' => $this->faker->unique()->safeEmail(),
        ]);
    }

    /** メール未認証 */
    public function unverified(): self
    {
        return $this->state(fn() => ['email_verified_at' => null]);
    }

    /** 任意メールアドレスを固定（テストで使いやすい） */
    public function withEmail(string $email): self
    {
        return $this->state(fn() => ['email' => $email]);
    }

    /** 表示名を固定 */
    public function withName(string $name): self
    {
        return $this->state(fn() => ['name' => $name]);
    }
}
