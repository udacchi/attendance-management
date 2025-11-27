<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Tests\Feature\Acceptance\Support\AttTestHelpers;

class Case08_ClockOutFeatureTest extends TestCase
{
    use AttTestHelpers;

    /** ①退勤ボタンが正しく機能する：押下後に「退勤済」(or 実装の表示文言) になる */
    public function test_clock_out_changes_status()
    {
        $u = $this->makeUser();

        // テスト対象日を「当日」として固定
        $this->nowFreeze('2025-11-24 17:59:00');

        // 09:00 に出勤している当日データ（退勤なし）を用意
        $this->seedAttendance($u, '2025-11-24', '09:00', null, [], 'working');

        // 打刻画面に「退勤」ボタンが見える
        $this->actingAs($u, 'web')
            ->get($this->ROUTE_STAMP)
            ->assertSee('退勤');

        // 退勤：実装は /attendance/punch に action を投げる
        $this->actingAs($u, 'web')
            ->post('/attendance/punch', ['action' => 'clock-out'])
            ->assertRedirect();

        // 退勤後の画面は同日のまま確認
        $this->nowFreeze('2025-11-24 18:00:00');

        // 実装の表示文言に合わせてここを選んでください。
        // 1) もしバッジが「退勤済」なら:
        $this->get($this->ROUTE_STAMP)->assertSee('退勤済');

        // 2) もし実装が「勤務外」を表示する仕様なら、上を消してこちらに差し替え:
        // $this->get($this->ROUTE_STAMP)->assertSee('勤務外');
    }

    /** ②退勤時刻が一覧に表示される */
    public function test_clock_out_time_appears_on_list()
    {
        $u = $this->makeUser();

        // 一覧は月で描くので、対象月を固定
        $this->nowFreeze('2025-11-24 18:01:00');

        // 09:00-18:00 の勤怠を投入（一覧に 18:00 が出る想定）
        $this->seedAttendance($u, '2025-11-24', '09:00', '18:00', [], 'checked_out');

        $this->actingAs($u, 'web')
            ->get($this->ROUTE_USER_ATT_LIST . '?month=2025-11')
            ->assertSee('18:00');
    }
}
