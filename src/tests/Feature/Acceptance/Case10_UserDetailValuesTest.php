<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Acceptance\Support\AttTestHelpers;

class Case10_UserDetailValuesTest extends TestCase
{
    use RefreshDatabase, AttTestHelpers;

    /** 名前がログインユーザーの氏名 */
    public function test_name_is_login_user()
    {
        $u = $this->makeUser(['name' => '山田太郎']);
        $this->actingAs($u, 'web')->get($this->ROUTE_USER_DETAIL . '?date=2025-11-20')->assertSee('山田太郎');
    }

    /** 日付が選択した日付 */
    public function test_date_is_selected_date()
    {
        $u = $this->makeUser();
        $this->actingAs($u, 'web')->get($this->ROUTE_USER_DETAIL . '?date=2025-11-20')->assertSee('2025')->assertSee('11')->assertSee('20');
    }

    /** 出勤・退勤が打刻と一致 */
    public function test_clock_in_out_match()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-20', '09:10', '18:05');
        $this->actingAs($u, 'web')->get($this->ROUTE_USER_DETAIL . '?date=2025-11-20')->assertSee('09:10')->assertSee('18:05');
    }

    /** 休憩が打刻と一致 */
    public function test_breaks_match()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-20', null, null, [['12:00', '12:45'], ['15:30', '15:40']]);
        $this->actingAs($u, 'web')->get($this->ROUTE_USER_DETAIL . '?date=2025-11-20')
            ->assertSee('12:00')->assertSee('12:45')->assertSee('15:30')->assertSee('15:40');
    }
}
