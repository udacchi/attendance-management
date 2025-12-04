<?php
// tests/Feature/Acceptance/_helpers/FeatureTestCase.php

namespace Tests\Feature\Acceptance\_helpers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\AttendanceDay;
use App\Models\BreakPeriod;
use Carbon\Carbon;

/**
 * 受け入れテスト共通の基底。ここにヘルパを集約します。
 *
 * @property string $ROUTE_STAMP
 * @property string $ROUTE_CLOCK_IN
 * @property string $ROUTE_CLOCK_OUT
 * @property string $ROUTE_BREAK_IN
 * @property string $ROUTE_BREAK_BACK
 * @property string $ROUTE_USER_ATT_LIST
 */


/**
 * 受け入れテスト共通の土台。
 * - ここで makeUser(), seedAttendance() などを提供します。
 * - ルートURLは setUp() で route() から解決してプロパティに格納します。
 */
class FeatureTestCase extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** ルート名（実装に合わせて必要なら変更） */
    protected string $ROUTE_STAMP         = '/attendance/stamp';
    protected string $ROUTE_CLOCK_IN      = '/attendance/clock-in';
    protected string $ROUTE_CLOCK_OUT     = '/attendance/clock-out';
    protected string $ROUTE_BREAK_IN      = '/attendance/break-in';
    protected string $ROUTE_BREAK_BACK    = '/attendance/break-back';
    protected string $ROUTE_USER_ATT_LIST = '/attendance/list';
    protected string $ROUTE_VERIFICATION_NOTICE = '/email/verify';
    protected string $ROUTE_VERIFICATION_SEND   = '/email/verification-notification';

    /**
     * テスト用ユーザー作成
     */
    protected function makeUser(array $overrides = []): User
    {
        return User::factory()->create($overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // 1) 打刻画面
        $this->ROUTE_STAMP = \Illuminate\Support\Facades\Route::has('attendance.stamp')
            ? route('attendance.stamp')
            : '/attendance/stamp';

        // 2) 一覧
        $this->ROUTE_USER_ATT_LIST = \Illuminate\Support\Facades\Route::has('attendance.list')
            ? route('attendance.list')
            : '/attendance/list';

        // 3) 出勤/退勤（存在しないなら既定のパスを保持：※ null は代入しない）
        $this->ROUTE_CLOCK_IN = \Illuminate\Support\Facades\Route::has('attendance.clock_in')
            ? route('attendance.clock_in')
            : '/attendance/clock-in';

        $this->ROUTE_CLOCK_OUT = \Illuminate\Support\Facades\Route::has('attendance.clock_out')
            ? route('attendance.clock_out')
            : '/attendance/clock-out';

        // 4) 休憩入/戻（名前が無ければ “既定のハイフン区切り” にフォールバック）
        $this->ROUTE_BREAK_IN = \Illuminate\Support\Facades\Route::has('attendance.break_in')
            ? route('attendance.break_in')
            : '/attendance/break-in';

        $this->ROUTE_BREAK_BACK = \Illuminate\Support\Facades\Route::has('attendance.break_back')
            ? route('attendance.break_back')
            : '/attendance/break-back';
    }


    /**
     * 勤怠1日分を作成
     *
     * @param User   $user  対象ユーザー
     * @param string $date  'YYYY-MM-DD'
     * @param ?string $clockIn  'HH:MM' | null
     * @param ?string $clockOut 'HH:MM' | null
     */
    protected function seedAttendance(User $user, string $date, ?string $clockIn, ?string $clockOut): AttendanceDay
    {
        $tz = config('app.timezone', 'Asia/Tokyo');

        $ci = $clockIn  ? Carbon::parse("$date $clockIn",  $tz) : null;
        $co = $clockOut ? Carbon::parse("$date $clockOut", $tz) : null;

        // 既存があれば拾う／なければ作成
        $day = AttendanceDay::firstOrCreate(
            ['user_id' => $user->id, 'work_date' => $date],
            ['note' => null]
        );

        if ($ci) $day->clock_in_at  = $ci;
        if ($co) $day->clock_out_at = $co;
        $day->save();

        return $day;
    }

    /**
     * 休憩明細を1件追加（一覧表示の検証用）
     *
     * @param AttendanceDay $day
     * @param string $start 'HH:MM'
     * @param string $end   'HH:MM'
     */
    protected function addBreak(AttendanceDay $day, string $start, string $end): BreakPeriod
    {
        $tz   = config('app.timezone', 'Asia/Tokyo');
        $date = Carbon::parse($day->work_date, $tz)->toDateString();

        return BreakPeriod::create([
            'attendance_day_id' => $day->id,
            'started_at'        => Carbon::parse("$date $start", $tz),
            'ended_at'          => Carbon::parse("$date $end",   $tz),
        ]);
    }

    /**
     * 管理者ユーザー作成
     */
    protected function makeAdmin(array $overrides = [])
    {
        return \App\Models\User::factory()->create(array_merge([
            'role'              => 'admin',
            'email_verified_at' => now(),
            'password'          => bcrypt('password'),
        ], $overrides));
    }

    /**
     * 複数のラベル候補のいずれかを含む <a> を探して href を返す（部分一致）
     */
    protected function findHrefByAnyLabel(string $html, array $labels): ?string
    {
        // 1) まず a タグを総当り
        if (preg_match_all('/<a\b[^>]*href=("|\')([^"\']+)\1[^>]*>(.*?)<\/a>/isu', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $a) {
                $text = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($a[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
                foreach ($labels as $label) {
                    $lab = trim(preg_replace('/\s+/u', ' ', (string)$label));
                    if ($lab !== '' && mb_stripos($text, $lab) !== false) {
                        return $a[2]; // href
                    }
                }
            }
        }

        // 2) ラベル近傍にある最初の <a href=...> をフォールバックで拾う
        foreach ($labels as $label) {
            if ($label === '' || $label === null) continue;
            $pos = mb_stripos($html, (string)$label);
            if ($pos !== false) {
                $slice = mb_substr($html, max(0, $pos - 400), 1000);
                if (preg_match('/<a\b[^>]*href=("|\')([^"\']+)\1/isu', $slice, $m2)) {
                    return $m2[2];
                }
            }
        }

        return null;
    }

    /**
     * 指定したキーワードのいずれかを含む <a> タグの href を返す
     */
    protected function findHrefByKeywords(string $html, array $keywords): ?string
    {
        // 1) aタグ 全取得
        if (preg_match_all('/<a\b[^>]*href=("|\')([^"\']+)\1[^>]*>(.*?)<\/a>/isu', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $a) {
                $text = trim(
                    preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($a[3], ENT_QUOTES, 'UTF-8')))
                );

                foreach ($keywords as $kw) {
                    $kw = trim((string)$kw);
                    if ($kw !== '' && mb_stripos($text, $kw) !== false) {
                        return $a[2]; // href
                    }
                }
            }
        }

        // 2) キーワード付近にある最初の a[href] を拾うフォールバック処理
        foreach ($keywords as $kw) {
            if (!$kw) continue;
            $pos = mb_stripos($html, $kw);
            if ($pos !== false) {
                $slice = mb_substr($html, max(0, $pos - 500), 1200);
                if (preg_match('/<a\b[^>]*href=("|\')([^"\']+)\1/isu', $slice, $m2)) {
                    return $m2[2];
                }
            }
        }

        return null;
    }
}
