<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;
use App\Models\AttendanceDay;

class Case14_AdminUserAndAttendanceViewsTest extends TestCase
{
    use RefreshDatabase;

    // 実装に合わせたルート
    private const ROUTE_ADMIN_USERS_LIST = '/admin/staff/list';       // 一般ユーザー一覧（氏名・メール）
    private const ROUTE_ADMIN_ATT_LIST   = '/admin/attendance/list';  // 勤怠一覧（＝日付ナビ）
    private const ROUTE_ADMIN_ATT_DETAIL = '/admin/attendance';       // /admin/attendance/{id}?date=

    // ===== ヘルパ =====
    private function makeAdmin(): User
    {
        return User::factory()->create([
            'name'     => '管理太郎',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);
    }

    private function makeUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name'  => $name,
            'email' => $email,
            'role'  => 'user',
        ]);
    }

    private function seedAttendance(User $u, string $date, ?string $in, ?string $out, string $note = ''): AttendanceDay
    {
        return AttendanceDay::create([
            'user_id'      => $u->id,
            'work_date'    => Carbon::parse($date)->startOfDay(),
            'clock_in_at'  => $in  ? Carbon::parse("$date $in")  : null,
            'clock_out_at' => $out ? Carbon::parse("$date $out") : null,
            'note'         => $note,
        ]);
    }

    /** 1) 管理者が全一般ユーザーの氏名・メールを確認できる（既にパスしている） */
    public function test_admin_sees_all_general_users_name_and_email(): void
    {
        $admin = $this->makeAdmin();
        $this->makeUser('山田太郎', 'taro@example.com');
        $this->makeUser('佐藤花子', 'hanako@example.com');

        $this->actingAs($admin, 'admin')
            ->get(self::ROUTE_ADMIN_USERS_LIST)
            ->assertOk()
            ->assertSee('山田太郎')
            ->assertSee('佐藤花子')
            ->assertSee('taro@example.com')
            ->assertSee('hanako@example.com');
    }

    /** 2) 選択ユーザーの勤怠情報が正しく表示される（実装は「日付詳細」画面で検証） */
    public function test_admin_user_attendance_view_is_shown_correctly(): void
    {
        $admin = $this->makeAdmin();
        $u     = $this->makeUser('山田太郎', 'taro@example.com');

        $this->seedAttendance($u, '2025-11-02', '09:00', '18:00', '通常勤務');
        $this->seedAttendance($u, '2025-11-15', '10:00', '19:00', '遅番');

        // まず 11/02 の詳細を確認
        $url = sprintf('%s/%d?date=%s', self::ROUTE_ADMIN_ATT_DETAIL, $u->id, '2025-11-02');

        $this->actingAs($admin, 'admin')
            ->get($url)
            ->assertOk()
            ->assertSee('勤怠詳細')     // 見出し
            ->assertSee('山田太郎')     // 氏名
            ->assertSee('2025年')       // 年（表記ゆれ吸収）
            ->assertSee('11月2日')      // 月日
            ->assertSee('09:00')        // 出勤
            ->assertSee('18:00');       // 退勤

        // 別日 11/15 も確認（ページ切り替え想定）
        $url = sprintf('%s/%d?date=%s', self::ROUTE_ADMIN_ATT_DETAIL, $u->id, '2025-11-15');
        $this->get($url)
            ->assertOk()
            ->assertSee('11月15日')
            ->assertSee('10:00')
            ->assertSee('19:00');
    }

    /** 3) 「前月」→ 実装は「前日」リンクなので ?date= の前日リンクを検証 */
    public function test_admin_prev_day_button_is_present(): void
    {
        $admin = $this->makeAdmin();

        // 2025-11-01 を基準日としてアクセス
        $base = Carbon::createFromFormat('Y-m-d', '2025-11-01');
        $prev = $base->copy()->subDay()->format('Y-m-d'); // 2025-10-31

        $this->actingAs($admin, 'admin')
            ->get(self::ROUTE_ADMIN_ATT_LIST . '?date=' . $base->format('Y-m-d'))
            ->assertOk()
            // HTMLに「?date=2025-10-31」のリンクが含まれる（スクショ上の実装に一致）
            ->assertSee('?date=' . $prev);
    }

    /** 4) 「翌月」→ 実装は「翌日」リンクなので ?date= の翌日リンクを検証 */
    public function test_admin_next_day_button_is_present(): void
    {
        $admin = $this->makeAdmin();

        $base = Carbon::createFromFormat('Y-m-d', '2025-11-01');
        $next = $base->copy()->addDay()->format('Y-m-d'); // 2025-11-02

        $this->actingAs($admin, 'admin')
            ->get(self::ROUTE_ADMIN_ATT_LIST . '?date=' . $base->format('Y-m-d'))
            ->assertOk()
            // HTMLに「?date=2025-11-02」のリンクが含まれる（スクショ上の実装に一致）
            ->assertSee('?date=' . $next);
    }

    /** 5) 「詳細」リンクでその日の勤怠詳細に遷移（既にパスしているが再掲） */
    public function test_admin_detail_link_goes_to_that_day_detail(): void
    {
        $admin = $this->makeAdmin();
        $u     = $this->makeUser('山田太郎', 'taro@example.com');
        $this->seedAttendance($u, '2025-11-20', '09:00', '18:00', '通常勤務');

        $url = sprintf('%s/%d?date=%s', self::ROUTE_ADMIN_ATT_DETAIL, $u->id, '2025-11-20');

        $this->actingAs($admin, 'admin')
            ->get($url)
            ->assertOk()
            ->assertSee('勤怠詳細')
            ->assertSee('11月20日')
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('山田太郎');
    }
}
