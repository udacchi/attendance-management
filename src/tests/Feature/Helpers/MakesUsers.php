<?php

namespace Tests\Helpers;

use App\Models\User;

trait MakesUsers
{
    protected function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'role' => 'user',
        ], $overrides));
    }

    protected function makeAdmin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'role' => 'admin',
        ], $overrides));
    }
}
