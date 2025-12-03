<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Tests\Feature\Acceptance\_helpers\Routes;
use App\Models\User;
use App\Models\AttendanceDay;
use Carbon\Carbon;

final class Case12_AdminAttendanceListTest extends FeatureTestCase
{
    use Routes;

    private function loginAdmin(): User
    {
        $a = User::factory()->admin()->create();
        $this->actingAs($a, 'admin');
        return $a;
    }

    /** ①その日になされた全ユーザーの勤怠情報が正確に確認できる */
    public function test_all_users_attendance_for_a_day(): void
    {
        $this->loginAdmin();
        $date = Carbon::today('Asia/Tokyo')->toDateString();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        AttendanceDay::factory()->create(['user_id' => $u1->id, 'work_date' => $date, 'clock_in_at' => Carbon::parse("$date 09:00")]);
        AttendanceDay::factory()->create(['user_id' => $u2->id, 'work_date' => $date, 'clock_in_at' => Carbon::parse("$date 09:30")]);

        $res = $this->get($this->ROUTE_ADMIN_ATT_LIST.'?date='.$date);
        $res->assertStatus(200)->assertSee('09:00')->assertSee('09:30');
    }

    /** ②遷移した際に現在の日付が表示される */
    public function test_date_shown_on_initial_navigation(): void
    {
        $this->loginAdmin();
        $date = Carbon::today('Asia/Tokyo')->toDateString();
        $res = $this->get($this->ROUTE_ADMIN_ATT_LIST.'?date='.$date);
        $res->assertStatus(200)->assertSee((string)Carbon::today('Asia/Tokyo')->year);
    }

    /** ③④「前日」あるいは「翌日」を押下した時に「前の日」あるいは「次の日」の勤怠情報が表示される */
    public function test_prev_next_day_navigation_links_exist(): void
    {
        $this->loginAdmin();
        $date = '2025-11-24';
        $res = $this->get($this->ROUTE_ADMIN_ATT_LIST.'?date='.$date);
        $res->assertStatus(200)->assertSee('前日')->assertSee('翌日');
    }
}
