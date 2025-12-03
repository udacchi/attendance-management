<?php

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use App\Models\AttendanceDay;
use App\Models\User;
use Carbon\Carbon;

class Case08_ClockOutFeatureTest extends FeatureTestCase
{
    /** /attendance/stamp の punchForm から退勤を送信する */
    protected function submitClockOutByForm(string $html)
    {
        // <form id="punchForm" action="..."> を拾う（なければ既定値）
        $action = '/attendance/punch';
        if (preg_match('/<form[^>]+id=("|\')punchForm\1[^>]*action=("|\')([^"\']+)\2/i', $html, $m)) {
            $action = $m[3];
        }
        return $this->post($action, ['action' => 'clock-out']);
    }

    /** ①退勤ボタンが正しく機能する */
    public function test_clock_out_changes_status()
    {
        Carbon::setTestNow(Carbon::parse('2025-12-04 10:00', 'Asia/Tokyo'));

        /** @var User $u */
        $u = $this->makeUser();
        // 当日 09:00 出勤・未退勤で作成
        $this->seedAttendance($u, Carbon::now()->toDateString(), '09:00', null);

        // 退勤ボタンが表示されていること（退勤前）
        $res = $this->actingAs($u, 'web')->get($this->ROUTE_STAMP)->assertSee('退勤');

        // 退勤送信 → リダイレクト期待
        $this->submitClockOutByForm($res->getContent())->assertRedirect();

        // DB: 退勤が入ったこと
        $ad = AttendanceDay::where('user_id', $u->id)
            ->whereDate('work_date', Carbon::now()->toDateString())
            ->firstOrFail();
        $this->assertNotNull($ad->clock_out_at, 'clock_out_at が null のままです');

        // 再描画：バッジ「退勤済」が見えることを確認
        // ※ 「退勤」を含まないチェックは「退勤済」に部分一致してしまうため不可
        $this->actingAs($u, 'web')->get($this->ROUTE_STAMP)->assertSee('退勤済')
            // 退勤ボタン用の value が出ていないことを確認（より確実）
            ->assertDontSee('value="clock-out"');
    }

    /** ②退勤時刻が勤怠一覧画面で確認できる */
    public function test_clock_out_time_appears_on_list()
    {
        Carbon::setTestNow(Carbon::parse('2025-12-04 18:05', 'Asia/Tokyo'));

        /** @var User $u */
        $u = $this->makeUser();

        $today = Carbon::now()->toDateString();
        // 09:00 出勤・未退勤
        $this->seedAttendance($u, $today, '09:00', null);

        // 退勤送信
        $res = $this->actingAs($u, 'web')->get($this->ROUTE_STAMP);
        $this->submitClockOutByForm($res->getContent())->assertRedirect();

        // 一覧に時刻（HH:MM）が出ていることを PHPunit のアサートで確認
        $list = $this->actingAs($u, 'web')->get($this->ROUTE_USER_ATT_LIST)->assertSee('勤怠一覧');
        $this->assertMatchesRegularExpression('/\b\d{2}:\d{2}\b/u', $list->getContent());
    }
}
