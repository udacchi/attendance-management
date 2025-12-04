<?php

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Carbon\Carbon;

class Case14_AdminUsersInfoTest extends FeatureTestCase
{
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

    protected function findUserRelatedLink(string $html, string $userName): ?string
    {
        if (preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/isu', $html, $trs)) {
            foreach ($trs[1] as $rowHtml) {
                if (mb_strpos(strip_tags($rowHtml), $userName) !== false) {
                    if (preg_match('/<a\b[^>]*href=("|\')([^"\']+)\1/isu', $rowHtml, $m)) {
                        return $m[2];
                    }
                }
            }
        }
        $pos = mb_stripos($html, $userName);
        if ($pos !== false) {
            $slice = mb_substr($html, max(0, $pos), 1000);
            if (preg_match('/<a\b[^>]*href=("|\')([^"\']+)\1/isu', $slice, $m)) {
                return $m[2];
            }
        }
        return null;
    }

    protected function resolveAdminStaffListUrl(): string
    {
        /** @var \App\Models\User $admin */
        $admin = $this->makeAdmin();
        $this->be($admin, 'admin');

        $candidates = [
            '/admin/staff',
            '/admin/staff/list',
            '/admin/users',
            '/admin/members',
            '/admin/staffs',
            '/admin/people',
        ];
        foreach ($candidates as $url) {
            $resp = $this->get($url);
            if ($resp->getStatusCode() === 200) return $url;
        }

        $entryCandidates = ['/', '/admin', '/admin/dashboard', '/dashboard', '/home'];
        foreach ($entryCandidates as $entry) {
            $res = $this->get($entry);
            if ($res->getStatusCode() !== 200) continue;
            $href = $this->findHrefByText($res->getContent(), 'スタッフ一覧');
            if ($href) return $href;
        }

        foreach ($entryCandidates as $entry) {
            $res = $this->get($entry);
            if ($res->getStatusCode() !== 200) continue;
            if (preg_match('/<a[^>]*href=("|\')([^"\']+)\1[^>]*>[^<]*スタッフ[^<]*<\/a>/isu', $res->getContent(), $m)) {
                return $m[2];
            }
        }

        $this->fail('スタッフ一覧ページのURLを解決できませんでした。');
    }

    /** ①管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる */
    public function test_admin_can_see_all_users_name_and_email()
    {
        $admin = $this->makeAdmin();
        $u1 = $this->makeUser(['name' => '山田 太郎', 'email' => 'taro@example.com']);
        $u2 = $this->makeUser(['name' => '佐藤 花子', 'email' => 'hanako@example.com']);

        $listUrl = $this->resolveAdminStaffListUrl();

        /** @var \App\Models\User $admin */
        $this->actingAs($admin, 'admin')
            ->get($listUrl)
            ->assertOk()
            ->assertSee('山田 太郎')
            ->assertSee('taro@example.com')
            ->assertSee('佐藤 花子')
            ->assertSee('hanako@example.com');
    }

    /** ②ユーザーの勤怠情報が正しく表示される */
    public function test_admin_can_see_user_attendance_list()
    {
        $admin  = $this->makeAdmin();
        $target = $this->makeUser(['name' => '勤怠テスト太郎', 'email' => 'att-target@example.com']);

        $today = Carbon::now('Asia/Tokyo')->startOfMonth()->addDays(2)->toDateString();
        $this->seedAttendance($target, $today, '09:00', '18:00');

        $listUrl = $this->resolveAdminStaffListUrl();

        /** @var \App\Models\User $admin */
        $html = $this->actingAs($admin, 'admin')->get($listUrl)->assertOk()->getContent();

        $attListHref = $this->findUserRelatedLink($html, '勤怠テスト太郎');
        $this->assertNotNull($attListHref, 'スタッフ一覧から対象ユーザーの勤怠ページへのリンクが見つかりません');

        $resp = $this->get($attListHref)->assertOk();
        $html2 = $resp->getContent();

        // Laravel8には assertSeeAnyText が無いので手動で「勤怠/出勤/勤務/一覧」いずれかの出現を確認
        $this->assertTrue(
            mb_strpos($html2, '勤怠') !== false
                || mb_strpos($html2, '出勤') !== false
                || mb_strpos($html2, '勤務') !== false
                || mb_strpos($html2, '一覧') !== false,
            '勤怠一覧ページらしさを示す文字が見つかりません'
        );

        // 日付は「YYYY/MM/DD」または「MM/DD(曜)」のどちらかで表示されていればOK
        $yyyy = Carbon::parse($today)->isoFormat('YYYY/MM/DD');
        $mmddWeek = Carbon::parse($today)->locale('ja')->isoFormat('MM/DD(ddd)');
        $this->assertTrue(
            mb_strpos($html2, $yyyy) !== false || mb_strpos($html2, $mmddWeek) !== false,
            "対象日が一覧に表示されていません（期待: {$yyyy} または {$mmddWeek}）"
        );
    }

    /** ③④「前月」あるいは「翌月」を押下した時に表示月の前月あるいは翌月の情報が表示される */
    public function test_prev_next_month_navigation_in_staff_attendance()
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser(['name' => '前後月テスト', 'email' => 'prevnext@example.com']);

        $now  = Carbon::now('Asia/Tokyo')->startOfMonth()->addDays(3);
        $prev = $now->copy()->subMonth()->toDateString();
        $curr = $now->copy()->toDateString();
        $next = $now->copy()->addMonth()->toDateString();

        $this->seedAttendance($user, $prev, '10:00', '19:00');
        $this->seedAttendance($user, $curr, '10:00', '19:00');
        $this->seedAttendance($user, $next, '10:00', '19:00');

        $listUrl = $this->resolveAdminStaffListUrl();

        /** @var \App\Models\User $admin */
        $indexHtml = $this->actingAs($admin, 'admin')->get($listUrl)->assertOk()->getContent();
        $attListHref = $this->findUserRelatedLink($indexHtml, '前後月テスト');
        $this->assertNotNull($attListHref, '勤怠ページへのリンクが見つかりません');

        $monthHtml = $this->get($attListHref)->assertOk()->getContent();
        $currYm = Carbon::parse($curr)->isoFormat('YYYY年M月');
        $this->assertStringContainsString($currYm, $monthHtml, '今月の年月表示が見つかりません');

        $prevHref = $this->findHrefByText($monthHtml, '前月');
        $this->assertNotNull($prevHref, '「前月」リンクが見つかりません');
        $prevHtml = $this->get($prevHref)->assertOk()->getContent();
        $prevYm = Carbon::parse($prev)->isoFormat('YYYY年M月');
        $this->assertStringContainsString($prevYm, $prevHtml, '前月の年月表示が正しくありません');

        $nextHref = $this->findHrefByText($prevHtml, '翌月');
        $this->assertNotNull($nextHref, '「翌月」リンクが見つかりません');
        $nextHtml = $this->get($nextHref)->assertOk()->getContent();

        $nextYmCandidate1 = $currYm;
        $nextYmCandidate2 = Carbon::parse($next)->isoFormat('YYYY年M月');
        $this->assertTrue(
            str_contains($nextHtml, $nextYmCandidate1) || str_contains($nextHtml, $nextYmCandidate2),
            '「翌月」遷移後の年月表示が想定外です'
        );
    }

    /** ⑤「詳細」を押下すると、その日の勤怠詳細画面に遷移する */
    public function test_detail_link_navigates_to_detail_page()
    {
        $admin = $this->makeAdmin();
        $user  = $this->makeUser(['name' => '詳細テスト', 'email' => 'detail@example.com']);

        $date = Carbon::now('Asia/Tokyo')->startOfMonth()->addDays(5)->toDateString();
        $this->seedAttendance($user, $date, '09:00', '18:00');

        $listUrl = $this->resolveAdminStaffListUrl();

        /** @var \App\Models\User $admin */
        $indexHtml = $this->actingAs($admin, 'admin')->get($listUrl)->assertOk()->getContent();
        $attListHref = $this->findUserRelatedLink($indexHtml, '詳細テスト');
        $this->assertNotNull($attListHref, '勤怠ページへのリンクが見つかりません');

        $listHtml = $this->get($attListHref)->assertOk()->getContent();

        // 一覧側の日付表示は「MM/DD(曜)」形式（サンプル: 12/06(土)）なので、こちらを期待にする
        $disp = Carbon::parse($date)->locale('ja')->isoFormat('MM/DD(ddd)');
        $this->assertStringContainsString($disp, $listHtml, "対象日が一覧に表示されていません（期待: {$disp}）");

        // 当該日の近傍から「詳細」リンクを抽出
        $pos = mb_stripos($listHtml, $disp);
        $this->assertNotFalse($pos, '対象日の位置が特定できません');
        $slice = mb_substr($listHtml, max(0, $pos), 1500);

        $detailHref = null;
        if (preg_match('/<a\b[^>]*href=("|\')([^"\']+)\1[^>]*>\s*詳細\s*<\/a>/isu', $slice, $m)) {
            $detailHref = $m[2];
        } else {
            if (preg_match('/<a\b[^>]*href=("|\')([^"\']+)\1/isu', $slice, $m2)) {
                $detailHref = $m2[2];
            }
        }
        $this->assertNotNull($detailHref, '対象日の「詳細」リンクが見つかりません');

        $detailHtml = $this->get($detailHref)->assertOk()->getContent();

        // タイトルは「勤怠詳細」または「勤怠詳細（管理者）」等を許容
        $this->assertTrue(
            str_contains($detailHtml, '勤怠詳細') || str_contains($detailHtml, '詳細'),
            '詳細ページのタイトル/見出しが見つかりません'
        );

        // 日付は「YYYY年M月D日」一続き もしくは 「YYYY年」と「M月D日」に分割表示のどちらでもOK
        $full = \Carbon\Carbon::parse($date)->isoFormat('YYYY年M月D日');
        $yearOnly = \Carbon\Carbon::parse($date)->isoFormat('YYYY年');
        $monthDay = \Carbon\Carbon::parse($date)->isoFormat('M月D日');

        $this->assertTrue(
            str_contains($detailHtml, $full) || (str_contains($detailHtml, $yearOnly) && str_contains($detailHtml, $monthDay)),
            "詳細ページの日付表記が見つかりません（期待: {$full} または {$yearOnly} + {$monthDay}）"
        );
    }
}
