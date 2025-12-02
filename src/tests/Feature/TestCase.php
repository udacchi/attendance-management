<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase; // ← 各テスト前後で migrate を自動実行（速いのはSQLite）

    protected function setUp(): void
    {
        parent::setUp();

        // 毎テストでフェイク
        Notification::fake();
        Mail::fake();

    }
}
