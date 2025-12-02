<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvSanityTest extends TestCase
{
    public function test_env_is_testing()
    {
        $this->assertTrue(app()->environment('testing'));
    }
}
