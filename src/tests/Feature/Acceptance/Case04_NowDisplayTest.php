<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Tests\Feature\Acceptance\_helpers\Routes;
use App\Models\User;
use Carbon\Carbon;

final class Case04_NowDisplayTest extends FeatureTestCase
{
    use Routes;

    /** ①現在の日時情報がUIと同じ形式で出力されている */
    public function test_now_text_is_displayed_in_stamp_page(): void
    {
        /** @var \App\Models\User $u */
        $u = User::factory()->create();
        $this->actingAs($u, 'web');

        // 表示時刻のフォーマットは Blade 側に合わせる（例：YYYY年M月D日 HH:mm）
        Carbon::setTestNow(Carbon::parse('2025-11-24 09:12:00', 'Asia/Tokyo'));

        $res = $this->get($this->ROUTE_STAMP);
        $res->assertStatus(200);
        // Blade 側が「YYYY年M月D日」「HH:mm」をそれぞれ出すケースを想定して両方確認
        $this->assertStringContainsString('2025年11月24日', $res->getContent());
        $this->assertStringContainsString('09:12', $res->getContent());
    }
}
