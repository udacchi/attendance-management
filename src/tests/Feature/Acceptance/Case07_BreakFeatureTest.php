<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Tests\Feature\Acceptance\Support\AttTestHelpers;

class Case07_BreakFeatureTest extends TestCase
{
    use AttTestHelpers;

    /** ① 休憩入りボタンが機能し、ステータスが「休憩中」になる */
    public function test_break_in_works()
    {
        $u = $this->makeUser();
        $this->nowFreeze('2025-11-24 12:00:00');

        // 出勤中の当日データを用意
        $this->seedAttendance($u, '2025-11-24', '09:00', null, [], 'working');

        // 休憩入り：実装は /attendance/punch に action を投げる
        $this->actingAs($u, 'web')
            ->post('/attendance/punch', ['action' => 'break-start'])
            ->assertRedirect();

        // 画面上が「休憩中」になっている
        $this->get($this->ROUTE_STAMP)->assertSee('休憩中');
    }

    /** ② 休憩は繰り返し可能（入→戻→入 ができる） */
    public function test_break_can_repeat()
    {
        $u = $this->makeUser();
        $this->nowFreeze('2025-11-24 12:00:00');

        $this->seedAttendance($u, '2025-11-24', '09:00', null, [], 'working');

        // 入
        $this->actingAs($u, 'web')
            ->post('/attendance/punch', ['action' => 'break-start'])
            ->assertRedirect();

        // 戻
        $this->nowFreeze('2025-11-24 12:30:00');
        $this->actingAs($u, 'web')
            ->post('/attendance/punch', ['action' => 'break-end'])
            ->assertRedirect();

        $this->get($this->ROUTE_STAMP)->assertSee('出勤中');

        // 再び入
        $this->nowFreeze('2025-11-24 15:00:00');
        $this->actingAs($u, 'web')
            ->post('/attendance/punch', ['action' => 'break-start'])
            ->assertRedirect();

        $this->get($this->ROUTE_STAMP)->assertSee('休憩中');
    }

    /** ③ 休憩戻が機能し、ステータスが「出勤中」になる */
    public function test_break_out_works()
    {
        $u = $this->makeUser();
        $this->nowFreeze('2025-11-24 12:00:00');

        $this->seedAttendance($u, '2025-11-24', '09:00', null, [], 'working');

        // 入
        $this->actingAs($u, 'web')
            ->post('/attendance/punch', ['action' => 'break-start'])
            ->assertRedirect();

        // 戻
        $this->nowFreeze('2025-11-24 12:30:00');
        $this->actingAs($u, 'web')
            ->post('/attendance/punch', ['action' => 'break-end'])
            ->assertRedirect();

        $this->get($this->ROUTE_STAMP)->assertSee('出勤中');
    }

    /** ④ 休憩戻は繰り返し可能（入→戻→入→戻） */
    public function test_break_out_can_repeat()
    {
        $u = $this->makeUser();
        $this->nowFreeze('2025-11-24 12:00:00');

        $this->seedAttendance($u, '2025-11-24', '09:00', null, [], 'working');

        // 1回目 入→戻
        $this->actingAs($u, 'web')->post('/attendance/punch', ['action' => 'break-start'])->assertRedirect();
        $this->nowFreeze('2025-11-24 12:30:00');
        $this->actingAs($u, 'web')->post('/attendance/punch', ['action' => 'break-end'])->assertRedirect();

        // 2回目 入→戻
        $this->nowFreeze('2025-11-24 15:00:00');
        $this->actingAs($u, 'web')->post('/attendance/punch', ['action' => 'break-start'])->assertRedirect();
        $this->nowFreeze('2025-11-24 15:10:00');
        $this->actingAs($u, 'web')->post('/attendance/punch', ['action' => 'break-end'])->assertRedirect();

        $this->get($this->ROUTE_STAMP)->assertSee('出勤中');
    }

    /**
     * ⑤ 休憩時刻が勤怠一覧画面で確認できる
     * 現行UIは「休憩の合計(00:30)」を月一覧に表示する仕様のため、
     * 具体的な「12:00 / 12:30」ではなく「00:30」を検証する。
     */
    public function test_break_times_appear_on_list()
    {
        $u = $this->makeUser();
        $this->nowFreeze('2025-11-24 10:00:00');

        // 休憩 12:00-12:30 を投入（退勤済にして合計が確定する想定）
        $this->seedAttendance(
            $u,
            '2025-11-24',
            '09:00',
            '18:00',
            [['12:00', '12:30']],
            'checked_out'
        );

        // 月固定で一覧を取得して、休憩合計「00:30」を確認
        $this->actingAs($u, 'web')
            ->get($this->ROUTE_USER_ATT_LIST . '?month=2025-11')
            ->assertSee('00:30');
    }
}
