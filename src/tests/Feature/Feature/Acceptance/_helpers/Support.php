<?php

declare(strict_types=1);

namespace Tests\Feature\Acceptance\_helpers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** すべての受け入れテストの親クラス */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase; // 各テスト前後でDBをリフレッシュ（.env.testing の接続を使用）
}
