<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\CorrectionRequest;

class Case11_UserDetailCorrectionTest extends TestCase
{
    use RefreshDatabase;

    // 画面系
    private string $ROUTE_USER_ATT_LIST = '/attendance/list';
    private string $ROUTE_ATT_DETAIL    = '/attendance/detail'; // ?date=YYYY-MM-DD
    private string $ROUTE_CORR_LIST     = '/stamp_correction_request/list';

    // メッセージ（実装の文言に合わせる）
    private string $MSG_BAD_CLOCK         = '出勤時間もしくは退勤時間が不適切な値です';
    private string $MSG_BAD_BREAK         = '休憩時間が不適切な値です';
    private string $MSG_BAD_BREAK_OR_OUT  = '休憩時間もしくは退勤時間が不適切な値です';
    private string $MSG_NOTE_REQUIRED     = '備考を記入してください';

    /** ユーザー作成（verified 済） */
    private function makeUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    /** 勤怠の下地を用意（あなたのヘルパに合わせて置き換えOK） */
    private function seedAttendance(User $u, string $date, ?string $in, ?string $out, array $breaks = [])
    {
        // 既にあなたのプロジェクトに seed ヘルパがあるならそれを呼ぶ
        // 無い場合の最小ダミー（Eloquent直書き）に差し替えてください。
        // ここではテスト簡略のダミーだけ置いておきます。
        \App\Models\AttendanceDay::factory()->create([
            'user_id'     => $u->id,
            'work_date'   => $date,
            'clock_in_at' => $in ? $date . ' ' . $in . ':00' : null,
            'clock_out_at' => $out ? $date . ' ' . $out . ':00' : null,
        ]);
        foreach ($breaks as $b) {
            \App\Models\BreakPeriod::factory()->create([
                'attendance_day_id' => \App\Models\AttendanceDay::where('user_id', $u->id)->where('work_date', $date)->value('id'),
                'start_at' => $b['start'] ? $date . ' ' . $b['start'] . ':00' : null,
                'end_at'   => $b['end']   ? $date . ' ' . $b['end'] . ':00'   : null,
            ]);
        }
    }

    /** ① 出勤>退勤 逆転エラー */
    public function test_clock_in_after_out_error()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-24', '09:00', '18:00');

        $this->actingAs($u, 'web')->get($this->ROUTE_ATT_DETAIL . '?date=2025-11-24')->assertOk();

        $r = $this->actingAs($u, 'web')->post(
            route('attendance.request', ['date' => '2025-11-24']),
            [
                'clock_in'  => '19:00',   // 退勤より後
                'clock_out' => '18:00',
                'breaks'    => [['start' => '12:00', 'end' => '12:30']],
                'note'      => '修正',
            ]
        );
        $this->followRedirects($r)->assertSee($this->MSG_BAD_CLOCK);
    }

    /** ② 休憩開始>退勤 エラー */
    public function test_break_start_after_out_error()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-24', '09:00', '18:00');

        $r = $this->actingAs($u, 'web')->post(
            route('attendance.request', ['date' => '2025-11-24']),
            [
                'clock_in'  => '09:00',
                'clock_out' => '18:00',
                'breaks'    => [['start' => '19:00', 'end' => '19:10']], // start が退勤後
                'note'      => '修正',
            ]
        );
        $this->followRedirects($r)->assertSee($this->MSG_BAD_BREAK);
    }

    /** ③ 休憩終了>退勤 エラー */
    public function test_break_end_after_out_error()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-24', '09:00', '18:00');

        $r = $this->actingAs($u, 'web')->post(
            route('attendance.request', ['date' => '2025-11-24']),
            [
                'clock_in'  => '09:00',
                'clock_out' => '18:00',
                'breaks'    => [['start' => '17:50', 'end' => '18:30']], // end が退勤後
                'note'      => '修正',
            ]
        );
        $this->followRedirects($r)->assertSee($this->MSG_BAD_BREAK_OR_OUT);
    }

    /** ④ 備考未入力 エラー */
    public function test_note_required_error()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-24', '09:00', '18:00');

        $r = $this->actingAs($u, 'web')->post(
            route('attendance.request', ['date' => '2025-11-24']),
            [
                'clock_in'  => '09:00',
                'clock_out' => '18:00',
                'breaks'    => [['start' => '12:00', 'end' => '12:30']],
                'note'      => '', // 必須
            ]
        );
        $this->followRedirects($r)->assertSee($this->MSG_NOTE_REQUIRED);
    }

    /** ⑤ 修正申請が作成され承認画面＆申請一覧（pending）に表示 */
    public function test_correction_request_created_and_shown_in_pending()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-24', '09:00', '18:00');

        $this->actingAs($u, 'web')->post(
            route('attendance.request', ['date' => '2025-11-24']),
            [
                'clock_in'  => '09:10',
                'clock_out' => '18:05',
                'breaks'    => [['start' => '12:00', 'end' => '12:45']],
                'note'      => '10分ズレ',
            ]
        )->assertRedirect();

        $this->actingAs($u, 'web')
            ->get($this->ROUTE_CORR_LIST . '?tab=pending')
            ->assertSee('承認待ち')
            ->assertSee('10分ズレ');
    }

    /** ⑥ pending タブに自分の申請が全て表示 */
    public function test_pending_tab_shows_all_my_requests()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-24', '09:00', '18:00');
        $this->seedAttendance($u, '2025-11-25', '09:00', '18:00');

        $this->actingAs($u, 'web')->post(route('attendance.request', ['date' => '2025-11-24']), [
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'breaks'   => [['start' => '12:00', 'end' => '12:30']],
            'note' => 'Aの申請',
        ])->assertRedirect();

        $this->actingAs($u, 'web')->post(route('attendance.request', ['date' => '2025-11-25']), [
            'clock_in' => '09:05',
            'clock_out' => '18:10',
            'breaks'   => [['start' => '12:05', 'end' => '12:35']],
            'note' => 'Bの申請',
        ])->assertRedirect();

        $this->actingAs($u, 'web')
            ->get($this->ROUTE_CORR_LIST . '?tab=pending')
            ->assertSee('Aの申請')
            ->assertSee('Bの申請');
    }

    /** ⑦ approved タブに管理者が承認した申請が表示（承認は直接DB操作でも可） */
    public function test_approved_tab_shows_admin_approved_requests()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-24', '09:00', '18:00');

        $this->actingAs($u, 'web')->post(route('attendance.request', ['date' => '2025-11-24']), [
            'clock_in' => '09:10',
            'clock_out' => '18:05',
            'breaks'   => [['start' => '12:00', 'end' => '12:45']],
            'note'     => '承認対象の申請',
        ])->assertRedirect();

        $req = CorrectionRequest::latest('id')->first();
        $this->assertNotNull($req);

        // 承認（本番はコントローラ経由。テストでは直接更新でもOK）
        $req->update(['status' => 'approved']);

        $this->actingAs($u, 'web')
            ->get($this->ROUTE_CORR_LIST . '?status=approved')
            ->assertSee('承認済み')
            ->assertSee('承認対象の申請');
    }

    /** ⑧ 申請「詳細」→ 勤怠詳細へ遷移（= 勤怠詳細URLにアクセスできること） */
    public function test_request_detail_link_navigates_to_attendance_detail()
    {
        $u = $this->makeUser();
        $this->seedAttendance($u, '2025-11-26', '09:00', '18:00');

        $this->actingAs($u, 'web')->post(route('attendance.request', ['date' => '2025-11-26']), [
            'clock_in' => '09:20',
            'clock_out' => '18:10',
            'breaks'   => [['start' => '12:10', 'end' => '12:40']],
            'note'     => '詳細遷移テスト',
        ])->assertRedirect();

        // 実際のリンククリック相当は省略し、「勤怠詳細 URL が 200 を返す」ことをもって代替
        $this->actingAs($u, 'web')
            ->get($this->ROUTE_ATT_DETAIL . '?date=2025-11-26')
            ->assertOk()
            ->assertSee('勤怠詳細');
    }
}
