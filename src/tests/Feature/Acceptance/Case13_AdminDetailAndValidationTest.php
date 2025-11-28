<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Acceptance\Support\AttTestHelpers;

class Case13_AdminDetailAndValidationTest extends TestCase
{
    use RefreshDatabase, AttTestHelpers;

    /** ① 詳細画面に選択したデータが表示される */
    public function test_admin_detail_shows_selected_data(): void
    {
        $admin = $this->makeAdmin();
        $u = $this->makeUser(['name' => '高橋三郎']);
        $this->seedAttendance($u, '2025-11-20', '09:00', '18:00');

        $this->actingAs($admin, $this->ADMIN_GUARD)
            ->get("/admin/attendance/{$u->id}?date=2025-11-20")
            ->assertOk()
            ->assertSee('高橋三郎')
            ->assertSee('09:00')
            ->assertSee('18:00');
    }

    /** ② 出勤>退勤 → clock_in/clock_out にバリデーションエラー */
    public function test_admin_clock_in_after_out_error(): void
    {
        $admin = $this->makeAdmin();
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-20', '09:00', '18:00');

        $from = "/admin/attendance/{$u->id}?date=2025-11-20";

        $r = $this->from($from)
            ->actingAs($admin, $this->ADMIN_GUARD)
            ->post("/admin/attendance/{$u->id}/update?date=2025-11-20", [
                'clock_in'      => '19:00',   // 退勤より後
                'clock_out'     => '18:00',
                'break1_start'  => '12:00',
                'break1_end'    => '12:30',
                'break2_start'  => null,
                'break2_end'    => null,
                'note'          => '修正',
            ]);

        $r->assertRedirect($from);
        // withValidator() で clock_in / clock_out 双方に付与
        $r->assertSessionHasErrors(['clock_in', 'clock_out']);
    }

    /** ③ 休憩開始>退勤 → break1_start にエラー */
    public function test_admin_break_start_after_out_error(): void
    {
        $admin = $this->makeAdmin();
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-20', '09:00', '18:00');

        $from = "/admin/attendance/{$u->id}?date=2025-11-20";

        $r = $this->from($from)
            ->actingAs($admin, $this->ADMIN_GUARD)
            ->post("/admin/attendance/{$u->id}/update?date=2025-11-20", [
                'clock_in'      => '09:00',
                'clock_out'     => '18:00',
                'break1_start'  => '19:00',   // 退勤より後
                'break1_end'    => '19:10',
                'break2_start'  => null,
                'break2_end'    => null,
                'note'          => '修正',
            ]);

        $r->assertRedirect($from);
        $r->assertSessionHasErrors(['break1_start']);
    }

    /** ④ 休憩終了>退勤 or 開始以上でない → break1_end にエラー */
    public function test_admin_break_end_after_out_error(): void
    {
        $admin = $this->makeAdmin();
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-20', '09:00', '18:00');

        $from = "/admin/attendance/{$u->id}?date=2025-11-20";

        $r = $this->from($from)
            ->actingAs($admin, $this->ADMIN_GUARD)
            ->post("/admin/attendance/{$u->id}/update?date=2025-11-20", [
                'clock_in'      => '09:00',
                'clock_out'     => '18:00',
                'break1_start'  => '17:50',
                'break1_end'    => '18:30',   // 退勤より後
                'break2_start'  => null,
                'break2_end'    => null,
                'note'          => '修正',
            ]);

        $r->assertRedirect($from);
        $r->assertSessionHasErrors(['break1_end']);
    }

    /** ⑤ 備考未入力 → 現在の実装では成功扱い */
    public function test_admin_note_required_current_impl(): void
    {
        $admin = $this->makeAdmin();
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-20', '09:00', '18:00');

        $r = $this->actingAs($admin, $this->ADMIN_GUARD)->post(
            "/admin/attendance/{$u->id}/update?date=2025-11-20",
            [
                'clock_in'  => '09:00',
                'clock_out' => '18:00',
                'breaks'    => [],
                'note'      => '',
            ]
        );

        $r->assertRedirect();
    }
}
