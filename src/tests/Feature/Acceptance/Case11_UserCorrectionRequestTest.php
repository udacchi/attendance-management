<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Acceptance\_helpers\FeatureTestCase;

class Case11_UserCorrectionRequestTest extends FeatureTestCase
{
    /**
     * 共通: 勤怠ベースデータを投入
     */
    private function seedBaseDay($user, string $date, string $in = '09:00', ?string $out = '18:00'): void
    {
        // FeatureTestCase に用意してある想定のシーダー
        // work_date = $date, clock_in/out のレコードを1件作る
        $this->seedAttendance($user, $date, $in, $out);
    }

    /**
     * 共通: 勤怠詳細画面の POST 送信ヘルパ
     * 実装の HTML から、/attendance/{date}/request への POST が正と読み取れる
     */
    private function detailSubmit(string $date, array $override = []): TestResponse
    {
        $payload = array_merge([
            'date'      => $date,
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
            // breaks は UI 上は複数行、未指定なら空を送れる
            'breaks'    => [
                ['start' => '12:00', 'end' => '13:00'],
            ],
            // 申請理由 (フォームの name は note)
            'note'      => 'テスト申請',
        ], $override);

        return $this->post("/attendance/{$date}/request", $payload);
    }

    /** ①出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される */
    public function test_clock_in_after_clock_out_is_invalid(): void
    {
        $u = $this->makeUser();
        $date = '2025-12-04';
        $this->seedBaseDay($u, $date, '09:00', '18:00');

        $res = $this->actingAs($u, 'web')->detailSubmit($date, [
            'clock_in'  => '19:00',
            'clock_out' => '18:00',
            'breaks'    => [['start' => '', 'end' => '']],
            'note'      => 'NG',
        ]);

        // バリデーション後、詳細画面に戻ってテーブル下にエラー文言が出る実装
        $this->followRedirects($res)
            ->assertOk()
            ->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    /** ②休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される */
    public function test_break_start_after_clock_out_is_invalid(): void
    {
        $u = $this->makeUser();
        $date = '2025-12-04';
        $this->seedBaseDay($u, $date, '09:00', '18:00');

        $res = $this->actingAs($u, 'web')->detailSubmit($date, [
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
            'breaks'    => [['start' => '19:00', 'end' => '19:30']],
            'note'      => 'NG',
        ]);

        $this->followRedirects($res)
            ->assertOk()
            ->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    /** ③休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される */
    public function test_break_end_after_clock_out_is_invalid(): void
    {
        $u = $this->makeUser();
        $date = '2025-12-04';
        $this->seedBaseDay($u, $date, '09:00', '18:00');

        $res = $this->actingAs($u, 'web')->detailSubmit($date, [
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
            'breaks'    => [['start' => '17:30', 'end' => '19:30']],
            'note'      => 'NG',
        ]);

        $this->followRedirects($res)
            ->assertOk()
            ->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    /** ④備考欄が未入力の場合のエラーメッセージが表示される */
    public function test_note_required(): void
    {
        $u = $this->makeUser();
        $date = '2025-12-04';
        $this->seedBaseDay($u, $date, '09:00', '18:00');

        $res = $this->actingAs($u, 'web')->detailSubmit($date, [
            'note' => '',
        ]);

        $this->followRedirects($res)
            ->assertOk()
            ->assertSee('備考を記入してください');
    }

    /** ⑤修正申請処理が実行される */
    public function test_submit_correction_request_success(): void
    {
        $u = $this->makeUser();
        $date = '2025-12-04';
        $this->seedBaseDay($u, $date, '09:00', '18:00');

        $this->actingAs($u, 'web')
            ->followRedirects($this->detailSubmit($date, [
                'clock_in'  => '09:05',
                'clock_out' => '18:00',
                'breaks'    => [['start' => '12:00', 'end' => '13:00']],
                'note'      => '申請登録OK',
            ]))
            ->assertOk();

        $this->assertDatabaseHas('correction_requests', [
            'requested_by' => $u->id,
            'status'       => 'pending',
        ]);
    }

    /** ⑥「承認待ち」にログインユーザーが行った申請が全て表示されていること*/
    public function test_pending_tab_lists_my_requests(): void
    {
        $u = $this->makeUser();

        $dateA = '2025-12-04';
        $this->seedBaseDay($u, $dateA);
        $this->actingAs($u, 'web')->followRedirects(
            $this->detailSubmit($dateA, [
                'clock_in'  => '09:05',
                'clock_out' => '18:00',
                'breaks'    => [['start' => '12:00', 'end' => '13:00']],
                'note'      => '申請A',
            ])
        )->assertOk();

        $dateB = '2025-12-05';
        $this->seedBaseDay($u, $dateB);
        $this->actingAs($u, 'web')->followRedirects(
            $this->detailSubmit($dateB, [
                'clock_in'  => '09:15',
                'clock_out' => '18:05',
                'breaks'    => [['start' => '12:10', 'end' => '13:05']],
                'note'      => '申請B',
            ])
        )->assertOk();

        $this->actingAs($u, 'web')
            ->get('/stamp_correction_request/list?status=pending')
            ->assertOk()
            ->assertSee('申請A')
            ->assertSee('申請B');
    }

    /** ⑦「承認済み」に管理者が承認した修正申請が全て表示されている */
    public function test_approved_tab_lists_admin_approved_requests(): void
    {
        $u = $this->makeUser();

        // 2件 pending 生成
        foreach (['2025-12-04', '2025-12-05'] as $i => $d) {
            $this->seedBaseDay($u, $d);
            $this->actingAs($u, 'web')->followRedirects(
                $this->detailSubmit($d, [
                    'clock_in'  => '09:0' . ($i + 1),
                    'clock_out' => '18:00',
                    'breaks'    => [['start' => '12:00', 'end' => '13:00']],
                    'note'      => $i === 0 ? '申請A' : '申請B',
                ])
            )->assertOk();
        }

        // 承認済みに更新（本来は承認APIを叩くが、ここでは一覧表示テストに集中）
        DB::table('correction_requests')
            ->where('requested_by', $u->id)
            ->update(['status' => 'approved']);

        $this->actingAs($u, 'web')
            ->get('/stamp_correction_request/list?status=approved')
            ->assertOk()
            ->assertSee('申請A')
            ->assertSee('申請B');
    }

    /** ⑧各申請の「詳細」を押下すると勤怠詳細画面に遷移する */
    public function test_detail_link_navigates_to_attendance_detail(): void
    {
        $u = $this->makeUser();
        $date = '2025-12-04';

        $this->seedBaseDay($u, $date);
        $this->actingAs($u, 'web')->followRedirects(
            $this->detailSubmit($date, [
                'clock_in'  => '09:10',
                'clock_out' => '18:10',
                'breaks'    => [['start' => '12:05', 'end' => '12:55']],
                'note'      => '申請A',
            ])
        )->assertOk();

        $html = $this->actingAs($u, 'web')
            ->get('/stamp_correction_request/list?status=pending')
            ->assertOk()
            ->getContent();

        // href="(http(s)://...)?/attendance/detail?date=YYYY-MM-DD"
        if (preg_match('/href=("|\')((?:https?:\/\/[^"\']+)?\/attendance\/detail\?date=\d{4}-\d{2}-\d{2})\1/u', $html, $m)) {
            $to = $m[2];
            // 絶対URLならそのまま、相対なら GET できる
            $this->get($to)->assertOk()->assertSee('勤怠詳細');
        } else {
            $this->fail('申請一覧に「詳細」リンクが見つかりませんでした。');
        }
    }
}
