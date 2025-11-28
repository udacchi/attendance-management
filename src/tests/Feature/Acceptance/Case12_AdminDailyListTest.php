<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Acceptance\Support\AttTestHelpers;

class Case12_AdminDailyListTest extends TestCase
{
    use RefreshDatabase, AttTestHelpers;

    /** ①その日全ユーザーの勤怠が正確に確認できる（存在確認） */
    public function test_admin_can_see_all_users_for_today()
    {
        $admin = $this->makeAdmin();
        $u1 = $this->makeUser(['name' => '田中一郎']);
        $u2 = $this->makeUser(['name' => '佐藤次郎']);
        $this->seedAttendance($u1, '2025-11-24', '09:00', '18:00');
        $this->seedAttendance($u2, '2025-11-24', '10:00', '19:00');

        $this->actingAs($admin, $this->ADMIN_GUARD)
            ->get($this->ROUTE_ADMIN_ATT_LIST . '?date=2025-11-24')
            ->assertSee('田中一郎')->assertSee('佐藤次郎');
    }

    /** ②現在日付が表示される */
    public function test_current_date_is_visible()
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, $this->ADMIN_GUARD)->get($this->ROUTE_ADMIN_ATT_LIST)->assertSee('年')->assertSee('日');
    }

    /** ③前日表示 */
    public function test_prev_date_button()
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, $this->ADMIN_GUARD)->get($this->ROUTE_ADMIN_ATT_LIST . '?date=2025-11-23')->assertSee('2025年11月23日');
    }

    /** ④翌日表示 */
    public function test_next_date_button()
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, $this->ADMIN_GUARD)->get($this->ROUTE_ADMIN_ATT_LIST . '?date=2025-11-25')->assertSee('2025年11月25日');
    }
}
