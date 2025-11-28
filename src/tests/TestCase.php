<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    // 好みでプロパティも
    protected string $USER_GUARD  = 'web';
    protected string $ADMIN_GUARD = 'admin';

    // 推奨：定数（どこからでも同じ値を使える）
    public const USER_GUARD  = 'web';
    public const ADMIN_GUARD = 'admin';
}
