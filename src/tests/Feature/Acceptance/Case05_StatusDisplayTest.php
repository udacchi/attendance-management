<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Acceptance\Support\AttTestHelpers;

class Case05_StatusDisplayTest extends TestCase
{
    use RefreshDatabase, AttTestHelpers;

    public function test_status_off_duty()
    {
        $this->nowFreeze('2025-11-24 08:00:00');
        $u = $this->makeUser();
        $this->actingAs($u, 'web')->get($this->ROUTE_STAMP)->assertSee('勤務外');
    }

    public function test_status_working()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-24', '09:00', null);
        $this->nowFreeze('2025-11-24 10:00:00');
        $this->actingAs($u, 'web')->get($this->ROUTE_STAMP)->assertSee('出勤中');
    }

    public function test_status_breaking()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-24', '09:00', null, [['10:15', null]]);
        $this->nowFreeze('2025-11-24 10:30:00');
        $this->actingAs($u, 'web')->get($this->ROUTE_STAMP)->assertSee('休憩中');
    }

    /** 退勤済を正しく表示（シードで状態を確定） */
    public function test_status_checked_out()
    {
        $this->nowFreeze('2025-11-24 09:00:00');
        $u = $this->makeUser();

        // 出勤→退勤のデータを投入（breakは作らない）
        $day = $this->seedAttendance($u, '2025-11-24', '09:00', '18:00');

        // 念のため未終了休憩を全部閉じる（あれば）
        $this->closeAllOpenBreaks($day, '17:59:00');

        // 退勤済みの実値を保存できるまで総当り（保存成功で day->status が確定）
        $actual = $this->setStatusCheckedOutByBruteForce($day);

        // 18:01 に固定して画面を確認
        $this->nowFreeze('2025-11-24 18:01:00');

        $res = $this->actingAs($u)->get($this->ROUTE_STAMP)->assertOk();

        // 退勤済や退勤済みがどちらでもOK（UI文言のゆれ吸収）
        $this->assertMatchesRegularExpression(
            '/退勤済(み)?/',
            $res->getContent(),
            'status=' . $actual . ' を保存しても退勤表示になりません。画面HTMLでバッジの実文言を確認してください。'
        );
    }
}
