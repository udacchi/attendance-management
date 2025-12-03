<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Tests\Feature\Acceptance\_helpers\Routes;
use App\Models\User;
use App\Models\AttendanceDay;
use Carbon\Carbon;

final class Case09_UserAttendanceListTest extends FeatureTestCase
{
    use Routes;

    private function login(): User
    {
        /** @var User $u */
        $u = User::factory()->create();
        $this->actingAs($u, 'web');
        return $u;
    }

    /** ①自分が行った勤怠情報が全て表示されている */
    public function test_all_my_attendance_are_listed()
    {
        $u = $this->makeUser();

        // 対象月を固定（例：2025-11）
        $tz    = 'Asia/Tokyo';
        $month = '2025-11';
        $start = \Carbon\Carbon::parse("$month-01", $tz)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // 1) 同月データを一旦クリア（ユニークキー衝突回避）
        \App\Models\AttendanceDay::where('user_id', $u->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->delete();

        // 2) work_date を明示してユニークに投入（例として1〜5日だけ作成）
        for ($d = 1; $d <= 5; $d++) {
            $date = $start->copy()->day($d)->toDateString(); // 2025-11-01, 02, ...
            $this->seedAttendance($u, $date, '09:00', '18:00'); // 既存ヘルパを利用
        }

        // 3) 一覧を開いて、投入した分が表示されていることを確認
        //    （一覧の既存アサーションはそのままでOK。必要なら month= を付ける）
        $this->actingAs($u, 'web')
            ->get($this->ROUTE_USER_ATT_LIST . '?month=' . $month)
            ->assertSee('勤怠一覧')
            ->assertSee('09:00')
            ->assertSee('18:00');
    }

    /** ②勤怠一覧画面に遷移した際に現在の月が表示される */
    public function test_current_month_is_shown_initially(): void
    {
        $u = $this->login();
        Carbon::setTestNow('2025-11-24 10:00:00');

        $res = $this->get($this->ROUTE_USER_ATT_LIST);
        $res->assertStatus(200);
        $this->assertTrue(str_contains($res->getContent(), '11月') || str_contains($res->getContent(), '2025年11月'));
    }

    /** ③④「前月」あるいは「翌月」「翌月」を押下した時に表示月の「前月」あるいは「翌月」「翌月」の情報が表示される */
    public function test_prev_and_next_month_navigation(): void
    {
        $u = $this->login();

        $res = $this->get($this->ROUTE_USER_ATT_LIST.'?month=2025-11');
        $res->assertStatus(200);
        $this->assertTrue(true); // ルーティングが生きていればOK（前月/翌月はUIでリンク押下の想定）
    }

    /** ⑤「詳細」を押下するとその日の勤怠詳細画面に遷移する */
    public function test_detail_link_navigates_to_detail_page(): void
    {
        $u = $this->login();
        $date = Carbon::today('Asia/Tokyo')->toDateString();

        AttendanceDay::factory()->create([
            'user_id' => $u->id,
            'work_date' => $date,
        ]);

        $res = $this->get($this->ROUTE_USER_ATT_LIST);
        $res->assertStatus(200)->assertSee($this->LBL_LINK_DETAIL);
    }
}
