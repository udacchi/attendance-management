<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance\_helpers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * 受け入れテストの共通ベースクラス。
 * RefreshDatabase をまとめて提供し、毎回書かなくて良いようにする。
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * 共通のセットアップ処理（必要なら追記）
     */
    protected function setUp(): void
    {
        parent::setUp();
        // 共通の前処理があればここに追加できる
    }
}
