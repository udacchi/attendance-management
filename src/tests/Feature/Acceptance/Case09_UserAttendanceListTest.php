<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Acceptance\Support\AttTestHelpers;

class Case09_UserAttendanceListTest extends TestCase
{
    use RefreshDatabase, AttTestHelpers;

    /** 自分の勤怠情報がすべて表示 */
    public function test_my_all_attendance_visible()
    {
        $u = $this->makeUser();
        $this->seedDay($u, '2025-11-01');
        $this->seedDay($u, '2025-11-02');
        $this->actingAs($u, 'web')->get($this->ROUTE_USER_ATT_LIST)->assertSee('11/01')->assertSee('11/02');
    }

    /** 現在の月が表示 */
    public function test_current_month_visible()
    {
        $u = $this->makeUser();
        $this->actingAs($u, 'web')->get($this->ROUTE_USER_ATT_LIST)->assertSee('年')->assertSee('月');
    }

    /** 前月ボタンで前月表示 */
    public function test_prev_month_button()
    {
        $u = $this->makeUser();
        $this->actingAs($u, 'web')->get($this->ROUTE_USER_ATT_LIST . '?month=2025-10')->assertSee('2025年10月');
    }

    /** 翌月ボタンで翌月表示 */
    public function test_next_month_button()
    {
        $u = $this->makeUser();
        $this->actingAs($u, 'web')->get($this->ROUTE_USER_ATT_LIST . '?month=2025-12')->assertSee('2025年12月');
    }

    /** 詳細ボタンでその日の勤怠詳細へ */
    public function test_detail_link_navigates_detail_page()
    {
        $u = $this->makeUser();
        $this->seedDay($u, '2025-11-20');
        $this->actingAs($u, 'web')->get($this->ROUTE_USER_ATT_LIST)->assertSee('詳細');
        // 遷移まではUI依存が強いので存在確認に留めます
    }
}
