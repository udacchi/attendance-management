<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Tests\Feature\Acceptance\_helpers\Routes;
use App\Models\User;
use App\Models\AttendanceDay;
use App\Models\BreakPeriod;
use Carbon\Carbon;

final class Case10_UserAttendanceDetailTest extends FeatureTestCase
{
    use Routes;

    private function prepare(User $u, string $date): AttendanceDay
    {
        $day = AttendanceDay::factory()->create([
            'user_id' => $u->id,
            'work_date' => $date,
            'clock_in_at' => Carbon::parse("$date 09:00"),
            'clock_out_at' => Carbon::parse("$date 18:00"),
            'status' => 'after',
            'note' => 'メモ',
        ]);
        BreakPeriod::factory()->create([
            'attendance_day_id' => $day->id,
            'started_at' => Carbon::parse("$date 12:00"),
            'ended_at' => Carbon::parse("$date 13:00"),
        ]);
        return $day;
    }

    /** ①勤怠詳細画面の「名前」「名前」がログインユーザーの氏名になっている */
    public function test_name_is_login_user(): void
    {
        /** @var User $u */
        $u = User::factory()->create(['name' => '山田太郎']);
        $this->actingAs($u, 'web');
        $date = Carbon::today('Asia/Tokyo')->toDateString();
        $this->prepare($u, $date);

        $res = $this->get($this->ROUTE_USER_ATT_DETAIL.'?date='.$date);
        $res->assertStatus(200)->assertSee('山田太郎');
    }

    /** ②勤怠詳細画面の「日付」が選択した日付になっている */
    public function test_date_is_selected_date(): void
    {
        /** @var User $u */
        $u = User::factory()->create();
        $this->actingAs($u, 'web');
        $date = '2025-11-20';
        $this->prepare($u, $date);

        $res = $this->get($this->ROUTE_USER_ATT_DETAIL.'?date='.$date);
        $res->assertStatus(200)->assertSee('2025')->assertSee('11')->assertSee('20');
    }

    /** ③「出勤・退勤」にて「出勤・退勤」にて記されている時間がログインユーザーログインユーザーの打刻と一致している */
    public function test_clock_in_out_match_records(): void
    {
        /** @var User $u */
        $u = User::factory()->create();
        $this->actingAs($u, 'web');
        $date = '2025-11-20';
        $this->prepare($u, $date);

        $res = $this->get($this->ROUTE_USER_ATT_DETAIL.'?date='.$date);
        $res->assertSee('09:00')->assertSee('18:00');
    }

    /** ④「休憩」にて記されている時間がログインユーザーの打刻と一致している */
    public function test_breaks_match_records(): void
    {
        /** @var User $u */
        $u = User::factory()->create();
        $this->actingAs($u, 'web');
        $date = '2025-11-20';
        $this->prepare($u, $date);

        $res = $this->get($this->ROUTE_USER_ATT_DETAIL.'?date='.$date);
        $res->assertSee('12:00')->assertSee('13:00');
    }
}
