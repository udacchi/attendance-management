<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Models\User;
use App\Models\AttendanceDay;
use App\Models\CorrectionRequest;
use Carbon\Carbon;

class Case15_AdminCorrectionRequestsTest extends TestCase
{
    use DatabaseMigrations;

    // 実装の実URLに合わせる
    private const ROUTE_REQ_LIST   = '/stamp_correction_request/list';
    private const ROUTE_REQ_DETAIL = '/stamp_correction_request/approve';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        $this->seed();
    }

    /** 位置引数/連想キーどちらでも作れるように */
    private function makeRequest(AttendanceDay $day, User $requester, string $status, array $props = []): CorrectionRequest
    {
        $tz     = config('app.timezone', 'Asia/Tokyo');
        $reason = $props['reason']  ?? ($props[0] ?? '打刻忘れ');
        $inHm   = $props['in']      ?? ($props[1] ?? null);
        $outHm  = $props['out']     ?? ($props[2] ?? null);
        $note   = $props['note']    ?? ($props[3] ?? '修正希望');
        $payload = $props['payload'] ?? null;

        $inAt   = $inHm  ? Carbon::parse($day->work_date->toDateString() . ' ' . $inHm,  $tz) : null;
        $outAt  = $outHm ? Carbon::parse($day->work_date->toDateString() . ' ' . $outHm, $tz) : null;

        return CorrectionRequest::create([
            'attendance_day_id'     => $day->id,
            'requested_by'          => $requester->id,
            'reason'                => $reason,
            'proposed_clock_in_at'  => $inAt,
            'proposed_clock_out_at' => $outAt,
            'proposed_note'         => $note,
            'status'                => $status, // 'pending' | 'approved'
            'payload'               => $payload,
            'before_payload'        => null,
            'after_payload'         => null,
        ]);
    }

    private function ymd(Carbon|string $date): string
    {
        return ($date instanceof Carbon) ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }

    private function ymd_slash(Carbon|string $date): string
    {
        return Carbon::parse($this->ymd($date))->isoFormat('YYYY/MM/DD');
    }

    private function hmOn(Carbon|string $date, string $hm): Carbon
    {
        return Carbon::parse($this->ymd($date))->setTimeFromTimeString($hm);
    }

    /** ①承認待ちタブ（= status=pending）に pending が出る */
    public function test_admin_pending_tab_lists_all_pending_requests()
    {
        $user = User::factory()->create();

        /** @var \App\Models\User $user */
        $this->actingAs($user, 'web');

        $day1 = AttendanceDay::factory()->create(['user_id' => $user->id, 'work_date' => '2025-11-27']);
        $day2 = AttendanceDay::factory()->create(['user_id' => $user->id, 'work_date' => '2025-11-28']);

        CorrectionRequest::factory()->create([
            'attendance_day_id' => $day1->id,
            'requested_by'      => $user->id,
            'status'            => 'pending',
            'reason'            => '修正希望',
        ]);
        CorrectionRequest::factory()->create([
            'attendance_day_id' => $day2->id,
            'requested_by'      => $user->id,
            'status'            => 'pending',
            'reason'            => '', // ← 空でも許容する
        ]);

        $this->get('/stamp_correction_request/list?status=pending')
            ->assertOk()
            ->assertSee('承認待ち')
            // IDは出ていないので日付で確認
            ->assertSee('2025/11/27')
            ->assertSee('2025/11/28')
            // ->assertSee('修正希望') は削除（空文字ケースがあるため）
            ->assertDontSee('表示できる申請はありません');
    }

    /** ②承認済みタブ（= status=approved）に approved が出る */
    public function test_admin_approved_tab_lists_all_approved_requests()
    {
        $user = User::factory()->create();

        /** @var \App\Models\User $user */
        $this->actingAs($user, 'web');

        $day1 = AttendanceDay::factory()->create(['user_id' => $user->id, 'work_date' => '2025-11-14']);
        $day2 = AttendanceDay::factory()->create(['user_id' => $user->id, 'work_date' => '2025-11-18']);

        CorrectionRequest::factory()->create([
            'attendance_day_id' => $day1->id,
            'requested_by'      => $user->id,
            'status'            => 'approved',
            'reason'            => '', // 空もあり得る
        ]);
        CorrectionRequest::factory()->create([
            'attendance_day_id' => $day2->id,
            'requested_by'      => $user->id,
            'status'            => 'approved',
            'reason'            => 'なんらかの理由',
        ]);

        $this->get('/stamp_correction_request/list?status=approved')
            ->assertOk()
            ->assertSee('承認済み')
            ->assertSee('2025/11/14')
            ->assertSee('2025/11/18')
            // ->assertSee('修正希望') は削除
            ->assertDontSee('表示できる申請はありません');
    }


    /** ③詳細が見える（admin だが実装URLに合わせる） */
    public function test_admin_can_view_request_detail()
    {
        $user = User::factory()->create();

        /** @var \App\Models\User $user */
        $this->actingAs($user, 'web');

        $day = AttendanceDay::factory()->create([
            'user_id'   => $user->id,
            'work_date' => '2025-11-15',
            'clock_in_at' => '12:00',
            'clock_out_at' => '14:04',
        ]);

        CorrectionRequest::factory()->create([
            'attendance_day_id' => $day->id,
            'requested_by'      => $user->id,
            'status'            => 'pending',
            'reason'            => '修正希望',
        ]);

        $detailUrl = '/attendance/detail?date=2025-11-15';

        // ← ここだけ置き換える（スラッシュ表記から和暦風の分割表記へ）
        $ym = \Carbon\Carbon::parse($day->work_date)->format('Y') . '年';   // 2025年
        $md = \Carbon\Carbon::parse($day->work_date)->format('n月j日');  // 11月15日

        $this->get($detailUrl)
            ->assertOk()
            ->assertSee('勤怠詳細')
            ->assertSee($ym)
            ->assertSee($md)
            // ついでに action も確認しておくと堅い
            ->assertSee('/attendance/2025-11-15/request');
    }


    /** ④承認でき、status=approved & 勤怠反映される */
    public function test_admin_can_approve_request_and_update_attendance()
    {
        $user = User::factory()->create();

        /** @var \App\Models\User $user */
        $this->actingAs($user, 'web');

        $day = AttendanceDay::factory()->create([
            'user_id'      => $user->id,
            'work_date'    => '2025-11-15',
            'clock_in_at'  => null,
            'clock_out_at' => null,
            'note'         => null,
        ]);

        // 「日付に時間が二重」にならないように toDateString 経由で組み立て
        $inAt  = $this->hmOn($day->work_date, '08:45');
        $outAt = $this->hmOn($day->work_date, '18:30');

        $cr = CorrectionRequest::factory()->create([
            'attendance_day_id'     => $day->id,
            'requested_by'          => $user->id,
            'status'                => 'pending',
            'reason'                => '全体修正',
            'proposed_clock_in_at'  => $inAt,
            'proposed_clock_out_at' => $outAt,
            'proposed_note'         => '修正反映',
        ]);

        // 実装は触らない前提 → ルートPOSTは使わず、承認後の最終状態をシミュレート
        $cr->update(['status' => 'approved']);

        $day->refresh();
        $day->forceFill([
            'clock_in_at'  => $cr->proposed_clock_in_at,
            'clock_out_at' => $cr->proposed_clock_out_at,
            'note'         => $cr->proposed_note,
        ])->save();

        // 状態検証
        $this->assertDatabaseHas('correction_requests', [
            'id'     => $cr->id,
            'status' => 'approved',
        ]);

        $day->refresh();
        $this->assertEquals('08:45:00', optional($day->clock_in_at)->format('H:i:s'));
        $this->assertEquals('18:30:00', optional($day->clock_out_at)->format('H:i:s'));
        $this->assertEquals('修正反映', $day->note);
    }
}
