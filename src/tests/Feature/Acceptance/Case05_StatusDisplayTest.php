<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Tests\Feature\Acceptance\_helpers\Routes;
use App\Models\User;
use App\Models\AttendanceDay;
use App\Models\BreakPeriod;
use Carbon\Carbon;

final class Case05_StatusDisplayTest extends FeatureTestCase
{
    use Routes;

    /** ユーザーログイン */
    private function login(): User
    {
        /** @var \App\Models\User $u */
        $u = User::factory()->create();
        $this->actingAs($u, 'web');
        return $u;
    }

    /** ①勤務外 */
    public function test_status_before(): void
    {
        $u = $this->login();
        // きょうの勤怠無し → 勤務外
        $res = $this->get($this->ROUTE_STAMP);
        $res->assertStatus(200);
        $this->assertStringContainsString($this->LBL_STATUS_BEFORE, $res->getContent());
    }

    /** ②出勤中 */
    public function test_status_working(): void
    {
        $u = $this->login();
        $today = Carbon::today('Asia/Tokyo')->toDateString();

        AttendanceDay::factory()->create([
            'user_id' => $u->id,
            'work_date' => $today,
            'clock_in_at' => Carbon::parse("$today 09:00"),
            'clock_out_at' => null,
            'status' => 'working',
        ]);

        $res = $this->get($this->ROUTE_STAMP);
        $this->assertStringContainsString($this->LBL_STATUS_WORKING, $res->getContent());
    }
    /** ③休憩中 */
    public function test_status_break(): void
    {
        $u = $this->login();
        $today = Carbon::today('Asia/Tokyo')->toDateString();

        $day = AttendanceDay::factory()->create([
            'user_id' => $u->id,
            'work_date' => $today,
            'clock_in_at' => Carbon::parse("$today 09:00"),
            'clock_out_at' => null,
            'status' => 'break',
        ]);

        BreakPeriod::factory()->create([
            'attendance_day_id' => $day->id,
            'started_at' => Carbon::parse("$today 12:00"),
            'ended_at' => null,
        ]);

        $res = $this->get($this->ROUTE_STAMP);
        $this->assertStringContainsString($this->LBL_STATUS_BREAK, $res->getContent());
    }

    /** ④退勤済 */
    public function test_status_after(): void
    {
        $u = $this->login();
        $today = Carbon::today('Asia/Tokyo')->toDateString();

        AttendanceDay::factory()->create([
            'user_id' => $u->id,
            'work_date' => $today,
            'clock_in_at' => Carbon::parse("$today 09:00"),
            'clock_out_at' => Carbon::parse("$today 18:00"),
            'status' => 'after',
        ]);

        $res = $this->get($this->ROUTE_STAMP);
        $this->assertStringContainsString($this->LBL_STATUS_AFTER, $res->getContent());
    }
}
