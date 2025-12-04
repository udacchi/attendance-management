<?php

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Carbon\Carbon;
use App\Models\AttendanceDay;
use App\Models\CorrectionRequest;

class Case15_AdminCorrectionRequestsTest extends FeatureTestCase
{
    /* =========================
       小道具：HTMLヘルパ
       ========================= */

    /** aタグのリンクテキストを部分一致で探して href を返す */
    protected function findHrefByText(string $html, string $label): ?string
    {
        if (preg_match_all('/<a\b[^>]*href=("|\')([^"\']+)\1[^>]*>(.*?)<\/a>/isu', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $a) {
                $text = trim(strip_tags($a[3]));
                if ($text !== '' && mb_strpos($text, $label) !== false) {
                    return $a[2];
                }
            }
        }
        return null;
    }

    /** ボタンラベルで<form>のactionを見つける（method未指定＝GETも許容） */
    protected function findFormActionByButton(string $html, string $buttonLabel): ?array
    {
        // まず当該ボタン含む付近のformを切り出す
        $pattern = '/<form\b[^>]*action=("|\')([^"\']+)\1[^>]*>(?:(?!<\/form>).)*?' .
            preg_quote($buttonLabel, '/') .
            '(?:(?!<\/form>).)*?<\/form>/isu';
        if (preg_match($pattern, $html, $m)) {
            $form = $m[0];
            $action = $m[2];
            $method = 'GET';
            if (preg_match('/\bmethod=("|\')([^"\']+)\1/i', $form, $mm)) {
                $method = strtoupper($mm[2]);
            }
            // hidden inputs も拾ってPOSTに使えるようにする
            $inputs = [];
            if (preg_match_all('/<input\b[^>]*>/isu', $form, $ins)) {
                foreach ($ins[0] as $in) {
                    if (
                        preg_match('/\btype=("|\')hidden\1/i', $in)
                        && preg_match('/\bname=("|\')([^"\']+)\1/i', $in, $nm)
                    ) {
                        $val = '';
                        if (preg_match('/\bvalue=("|\')([^"\']*)\1/i', $in, $vm)) {
                            $val = html_entity_decode($vm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                        $inputs[$nm[2]] = $val;
                    }
                }
            }
            return [$action, $method, $inputs];
        }
        return null;
    }

    /* =========================
       小道具：URL解決＆データ作成
       ========================= */

    /** 申請一覧ページURLを解決（候補総当り→ナビから「申請一覧」を発見） */
    protected function resolveRequestListUrl(): string
    {
        /** @var \App\Models\User $admin */
        $admin = $this->makeAdmin();
        $this->be($admin, 'admin');

        $candidates = [
            '/stamp_correction_request/list',
            '/admin/stamp_correction_request/list',
            '/correction_request/list',
            '/admin/correction_request/list',
        ];
        foreach ($candidates as $url) {
            $r = $this->get($url);
            if ($r->getStatusCode() === 200) return $url;
        }

        $entries = ['/', '/admin', '/admin/dashboard', '/dashboard', '/home', '/attendance/list', '/admin/attendance/list'];
        foreach ($entries as $entry) {
            $res = $this->get($entry);
            if ($res->getStatusCode() !== 200) continue;
            $href = $this->findHrefByText($res->getContent(), '申請一覧');
            if ($href) return $href;
        }

        $this->fail('申請一覧ページのURLを解決できませんでした。ナビに「申請一覧」リンクを表示してください。');
    }

    /** 指定ユーザー・日付の AttendanceDay を取得（なければ seed して取得） */
    protected function ensureAttendanceDay($user, string $date, ?string $cin = '09:00', ?string $cout = '18:00'): AttendanceDay
    {
        // まずseed（既にあっても上書きされない想定のヘルパ）
        $this->seedAttendance($user, $date, $cin, $cout);

        // カラム名の差異に耐える（work_date / date / target_date）
        $q = AttendanceDay::where('user_id', $user->id);
        $day = $q->whereDate('work_date', $date)->first()
            ?? AttendanceDay::where('user_id', $user->id)->whereDate('date', $date)->first()
            ?? AttendanceDay::where('user_id', $user->id)->whereDate('target_date', $date)->first()
            ?? AttendanceDay::where('user_id', $user->id)->orderByDesc('id')->first();

        $this->assertNotNull($day, 'AttendanceDayが見つかりません');
        return $day;
    }

    /** 補正申請を作成（現状のスキーマに合わせて最低限のフィールドのみ） */
    protected function makeRequest($user, AttendanceDay $day, string $status, array $overrides = []): CorrectionRequest
    {
        $base = [
            'attendance_day_id'    => $day->id,
            'requested_by'         => $user->id,
            'reason'               => $overrides['reason'] ?? 'テスト理由 ' . uniqid(),
            'proposed_clock_in_at' => $overrides['proposed_clock_in_at'] ?? null,
            'proposed_clock_out_at' => $overrides['proposed_clock_out_at'] ?? null,
            'proposed_note'        => $overrides['proposed_note'] ?? null,
            'status'               => $status, // 'pending' | 'approved'
            'payload'              => $overrides['payload'] ?? null,
            'before_payload'       => $overrides['before_payload'] ?? null,
            'after_payload'        => $overrides['after_payload'] ?? null,
        ];
        return CorrectionRequest::create($base);
    }

    /* =========================
       テストケース
       ========================= */

    /** ①承認待ちの修正申請が全て表示されている */
    public function test_admin_pending_tab_lists_all_pending_requests()
    {
        $admin = $this->makeAdmin();
        $u1 = $this->makeUser(['name' => '申請太郎', 'email' => 'req1@example.com']);
        $u2 = $this->makeUser(['name' => '申請花子', 'email' => 'req2@example.com']);

        $date = \Carbon\Carbon::now('Asia/Tokyo')->toDateString();
        $d1 = $this->ensureAttendanceDay($u1, $date);
        $d2 = $this->ensureAttendanceDay($u2, $date);

        // 理由は画面に出ない実装なのでダミーでOK
        $this->makeRequest($u1, $d1, 'pending', ['reason' => '理由A']);
        $this->makeRequest($u2, $d2, 'pending', ['reason' => '理由B']);

        $listUrl = $this->resolveRequestListUrl();

        /** @var \App\Models\User $admin */
        $html = $this->actingAs($admin, 'admin')->get($listUrl)->assertOk()->getContent();
        $pendingHref = $this->findHrefByText($html, '承認待ち') ?? ($listUrl . '?status=pending');

        $this->get($pendingHref)
            ->assertOk()
            ->assertSee('承認待ち')
            // 一覧は氏名とステータス・日付などが表示される前提
            ->assertSee('申請太郎')
            ->assertSee('申請花子');
    }

    /** ②承認済みの修正申請が全て表示されている */
    public function test_admin_approved_tab_lists_all_approved_requests()
    {
        $admin = $this->makeAdmin();
        $u1 = $this->makeUser(['name' => '承認太郎', 'email' => 'ap1@example.com']);
        $u2 = $this->makeUser(['name' => '承認花子', 'email' => 'ap2@example.com']);

        $date = \Carbon\Carbon::now('Asia/Tokyo')->subDay()->toDateString();
        $d1 = $this->ensureAttendanceDay($u1, $date);
        $d2 = $this->ensureAttendanceDay($u2, $date);

        // 理由は画面に出ない実装なのでダミーでOK
        $this->makeRequest($u1, $d1, 'approved', ['reason' => '承認理由X']);
        $this->makeRequest($u2, $d2, 'approved', ['reason' => '承認理由Y']);

        $listUrl = $this->resolveRequestListUrl();

        /** @var \App\Models\User $admin */
        $html = $this->actingAs($admin, 'admin')->get($listUrl)->assertOk()->getContent();
        $approvedHref = $this->findHrefByText($html, '承認済み') ?? ($listUrl . '?status=approved');

        $this->get($approvedHref)
            ->assertOk()
            ->assertSee('承認済み')
            // 一覧は氏名が表示される前提で検証
            ->assertSee('承認太郎')
            ->assertSee('承認花子');
    }

    /** ③修正申請の詳細内容が正しく表示されている */
    public function test_admin_can_view_request_detail()
    {
        $admin = $this->makeAdmin();
        $u = $this->makeUser(['name' => '詳細くん', 'email' => 'detail@example.com']);

        $date = Carbon::now('Asia/Tokyo')->toDateString();
        $day  = $this->ensureAttendanceDay($u, $date);
        $req  = $this->makeRequest($u, $day, 'pending', [
            'reason' => '詳細理由Z',
            'proposed_clock_in_at'  => $date . ' 10:00:00',
            'proposed_clock_out_at' => $date . ' 19:00:00',
            'proposed_note'         => '備考Z',
        ]);

        $listUrl = $this->resolveRequestListUrl();

        // 承認待ちタブ→「詳細」リンクを辿る
        /** @var \App\Models\User $admin */
        $html = $this->actingAs($admin, 'admin')->get($listUrl)->assertOk()->getContent();
        $pendingHref = $this->findHrefByText($html, '承認待ち') ?? ($listUrl . '?status=pending');
        $tabHtml = $this->get($pendingHref)->assertOk()->getContent();

        // 「詳細」リンクを抽出（最初のものを利用）
        $detailHref = $this->findHrefByText($tabHtml, '詳細');
        $this->assertNotNull($detailHref, '修正申請の「詳細」リンクが見つかりません');

        $detailHtml = $this->get($detailHref)->assertOk()->getContent();
        $this->assertTrue(
            str_contains($detailHtml, '修正申請') || str_contains($detailHtml, '申請') || str_contains($detailHtml, '勤怠詳細'),
            '詳細画面らしさを示す見出しが見つかりません'
        );
        $this->assertStringContainsString('詳細理由Z', $detailHtml);
        $this->assertTrue(str_contains($detailHtml, '10:00') && str_contains($detailHtml, '19:00'));
    }

    /** ④修正申請の承認処理が正しく行われる */
    public function test_admin_can_approve_request_and_update_attendance()
    {
        $admin = $this->makeAdmin();
        $u     = $this->makeUser(['name' => '承認テスト', 'email' => 'approve@example.com']);

        $date  = Carbon::now('Asia/Tokyo')->toDateString();
        $day   = $this->ensureAttendanceDay($u, $date, '09:00', '18:00');

        // 承認すると 09:00→10:00 / 18:00→19:00 に更新される提案
        $req = $this->makeRequest($u, $day, 'pending', [
            'reason' => '承認する理由',
            'proposed_clock_in_at'  => $date . ' 10:00:00',
            'proposed_clock_out_at' => $date . ' 19:00:00',
            'proposed_note'         => '承認後メモ',
        ]);

        $listUrl = $this->resolveRequestListUrl();

        // 承認待ちタブを開いて、対象の「詳細」へ
        /** @var \App\Models\User $admin */
        $html = $this->actingAs($admin, 'admin')->get($listUrl)->assertOk()->getContent();
        $pendingHref = $this->findHrefByText($html, '承認待ち') ?? ($listUrl . '?status=pending');
        $tabHtml = $this->get($pendingHref)->assertOk()->getContent();

        $detailHref = $this->findHrefByText($tabHtml, '詳細');
        $this->assertNotNull($detailHref, '修正申請の「詳細」リンクが見つかりません');

        // 詳細ページから「承認」ボタンの form action を発見して submit
        $detailHtml = $this->get($detailHref)->assertOk()->getContent();
        $form = $this->findFormActionByButton($detailHtml, '承認');
        $this->assertNotNull($form, '「承認」ボタンのあるフォームが見つかりません');
        [$action, $method, $hidden] = $form;

        // 送信（methodに応じて）
        if ($method === 'POST') {
            $this->post($action, $hidden)->assertRedirect(); // 多くの実装でリダイレクトが返る
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            $this->call($method, $action, $hidden)->assertRedirect();
        } else {
            $this->get($action)->assertStatus(200); // まれにGET承認実装の場合
        }

        // DBが更新されたことを確認（AttendanceDay & CorrectionRequest）
        $day->refresh();
        // 時刻文字列はH:i比較に正規化
        $cin = $day->clock_in_at ? Carbon::parse($day->clock_in_at)->format('H:i') : null;
        $cout = $day->clock_out_at ? Carbon::parse($day->clock_out_at)->format('H:i') : null;

        $this->assertSame('10:00', $cin, '承認後の出勤時刻が更新されていません');
        $this->assertSame('19:00', $cout, '承認後の退勤時刻が更新されていません');

        $req->refresh();
        $this->assertSame('approved', $req->status, '申請のステータスがapprovedになっていません');
    }
}
