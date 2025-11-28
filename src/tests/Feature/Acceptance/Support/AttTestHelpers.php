<?php

namespace Tests\Feature\Acceptance\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\AttendanceDay;
use App\Models\BreakPeriod;

trait AttTestHelpers
{
    // ===== ルート(必要に応じて変更) =====
    protected string $ROUTE_REGISTER          = '/register';
    protected string $ROUTE_LOGIN             = '/login';
    protected string $ROUTE_STAMP             = '/attendance/stamp';
    protected string $ROUTE_USER_ATT_LIST     = '/attendance/list';
    protected string $ROUTE_USER_DETAIL       = '/attendance/detail';         // ?date=YYYY-MM-DD
    protected string $ROUTE_DETAIL_UPDATE     = '/attendance/detail/update';  // POST ?date=YYYY-MM-DD
    protected string $ROUTE_CLOCK_IN          = '/attendance/clock-in';
    protected string $ROUTE_BREAK_IN          = '/attendance/break-in';
    protected string $ROUTE_BREAK_OUT         = '/attendance/break-out';
    protected string $ROUTE_CLOCK_OUT         = '/attendance/clock-out';

    protected string $ROUTE_ADMIN_LOGIN       = '/admin/login';
    protected string $ROUTE_ADMIN_ATT_LIST    = '/admin/attendance/list';     // ?date=YYYY-MM-DD
    protected string $ROUTE_ADMIN_USERS       = '/admin/users';
    protected string $ROUTE_ADMIN_STAFF_MONTH = '/admin/attendance/staff';    // /{id}?month=YYYY-MM

    protected string $ROUTE_CORR_LIST               = '/stamp_correction_request/list';
    protected string $ROUTE_CORR_APPROVE_SHOW_NAME  = 'stamp_correction_request.approve';
    protected string $ROUTE_CORR_APPROVE_STORE_NAME = 'stamp_correction_request.approve.store';

    protected string $ADMIN_GUARD = 'admin'; // 別ガードなら 'admin'

    // ===== メッセージ(必要に応じて変更) =====
    protected string $MSG_REQUIRE_NAME      = 'お名前を入力してください';
    protected string $MSG_REQUIRE_EMAIL     = 'メールアドレスを入力してください';
    protected string $MSG_REQUIRE_PASS      = 'パスワードを入力してください';
    protected string $MSG_PASS_MIN          = 'パスワードは8文字以上で入力してください';
    protected string $MSG_PASS_CONFIRM      = 'パスワードと一致しません';
    protected string $MSG_LOGIN_NOT_FOUND   = 'ログイン情報が登録されていません';
    protected string $MSG_BAD_CLOCK         = '出勤時間が不適切な値です';
    protected string $MSG_BAD_BREAK         = '休憩時間が不適切な値です';
    protected string $MSG_BAD_BREAK_OR_OUT  = '休憩時間もしくは退勤時間が不適切な値です';
    protected string $MSG_NOTE_REQUIRED     = '備考を記入してください';

    

    protected function tz(): string
    {
        return config('app.timezone', 'Asia/Tokyo');
    }
    protected function nowFreeze(string $dt): void
    {
        Carbon::setTestNow(Carbon::parse($dt, $this->tz()));
    }

    protected function makeUser(array $over = []): User
    {
        return User::factory()->create(array_merge([
            'name' => '一般 太郎',
            'email' => 'user' . Str::random(6) . '@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'role' => 'user',
        ], $over));
    }

    protected function makeAdmin(array $over = []): User
    {
        return User::factory()->create(array_merge([
            'name' => '管理 花子',
            'email' => 'admin' . Str::random(6) . '@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ], $over));
    }

    protected function seedAttendance(
        User $user,
        string $date,
        ?string $clockIn = null,
        ?string $clockOut = null,
        array $breakPairs = [],
        ?string $status = null
    ): AttendanceDay {
        // --- 既存の自動判定はそのまま ---
        if ($status === null) {
            if (!$clockIn && !$clockOut) {
                $status = 'off';
            } elseif ($clockIn && !$clockOut) {
                $last = end($breakPairs);
                if ($last && isset($last[0]) && (!isset($last[1]) || $last[1] === null || $last[1] === '')) {
                    $status = 'breaking';
                } else {
                    $status = 'working';
                }
            } else {
                $status = 'checked_out';
            }
        }

        // --- DB保存用に正規化（モデル定数 or 既定マップ） ---
        $semantic = $status;
        $statusForDb = $this->statusValue($semantic);

        // ★ 退勤済だけは “実装ごとのENUM” に合わせてリトライ
        if ($semantic === 'checked_out') {
            $candidates = [
                // モデル定数があるなら最優先
                (function () {
                    $cls = \App\Models\AttendanceDay::class;
                    $full = $cls . '::STATUS_CHECKED_OUT';
                    return defined($full) ? constant($full) : null;
                })(),
                // 文字列候補（よくあるバリエーション）
                'checkedout',
                'checkout',
                'checked_out',
                'checked-out',
                'out',
                'left',
                'done',
                'finished',
                '退勤済',
                // 数値候補（tinyint系）
                3,
                9,
            ];
            // 先頭に既定値も入れておく
            array_unshift($candidates, $statusForDb);

            // 試行
            foreach ($candidates as $cand) {
                if ($cand === null) continue;
                try {
                    $day = AttendanceDay::create([
                        'user_id' => $user->id,
                        'work_date' => $date,
                        'clock_in_at'  => $clockIn  ? Carbon::parse("$date $clockIn", $this->tz()) : null,
                        'clock_out_at' => $clockOut ? Carbon::parse("$date $clockOut", $this->tz()) : null,
                        'status' => $cand,
                        'total_work_minutes' => null,
                        'total_break_minutes' => null,
                        'note' => 'メモ',
                    ]);
                    // 休憩も投入
                    foreach ($breakPairs as [$s, $e]) {
                        BreakPeriod::create([
                            'attendance_day_id' => $day->id,
                            'started_at' => Carbon::parse("$date $s", $this->tz()),
                            'ended_at'   => $e ? Carbon::parse("$date $e", $this->tz()) : null,
                        ]);
                    }
                    return $day; // 成功したら即返す
                } catch (QueryException $e) {
                    // 次の候補へ
                }
            }
            // ここまで来たら最後に例外
            throw new \RuntimeException('Could not determine valid checked_out status value for DB.');
        }

        // ★ 退勤済み以外は従来どおり一発保存
        $day = AttendanceDay::create([
            'user_id' => $user->id,
            'work_date' => $date,
            'clock_in_at'  => $clockIn  ? Carbon::parse("$date $clockIn", $this->tz()) : null,
            'clock_out_at' => $clockOut ? Carbon::parse("$date $clockOut", $this->tz()) : null,
            'status' => $statusForDb,
            'total_work_minutes' => null,
            'total_break_minutes' => null,
            'note' => 'メモ',
        ]);
        foreach ($breakPairs as [$s, $e]) {
            BreakPeriod::create([
                'attendance_day_id' => $day->id,
                'started_at' => Carbon::parse("$date $s", $this->tz()),
                'ended_at'   => $e ? Carbon::parse("$date $e", $this->tz()) : null,
            ]);
        }
        return $day;
    }

    protected function statusValue(string $semantic)
    {
        // 1) モデル定数があるなら最優先で使う（存在チェック＋文字列参照でIDE警告回避）
        $cls = \App\Models\AttendanceDay::class;
        $constNameMap = [
            'off'         => 'STATUS_OFF',
            'working'     => 'STATUS_WORKING',
            'breaking'    => 'STATUS_BREAK',
            'checked_out' => 'STATUS_CHECKED_OUT',
        ];
        if (isset($constNameMap[$semantic])) {
            $full = $cls . '::' . $constNameMap[$semantic];
            if (defined($full)) {
                return constant($full);
            }
        }

        // 2) 固定マップ（あなたのDBに合わせる）
        //   ※ ここがENUM/SET/文字列カラムの実値。違っていたらここだけ直せばOK
        $mapString = [
            'off'         => 'off',
            'working'     => 'working',
            'breaking'    => 'break',       // ← 'breaking' ではなく 'break'
            'checked_out' => 'checkedout',  // ← 'checked_out' ではなく 'checkedout'
        ];

        return $mapString[$semantic];
    }

    protected function forceCheckedOut(\App\Models\AttendanceDay $day): void
    {
        // モデル定数があれば最優先
        $const = null;
        $cls = \App\Models\AttendanceDay::class;
        $full = $cls . '::STATUS_CHECKED_OUT';
        if (defined($full)) {
            $const = constant($full);
        }

        // よくある候補を（既定→文字列→数値）で総当り
        $candidates = array_values(array_filter([
            $const,
            // よく見る表記ゆれ（順序も一般的な出現順）
            'checked_out',
            'checkedout',
            'checkout',
            'left',
            'out',
            'finished',
            'done',
            '退勤済',
            // 数値（tinyint の可能性）
            3,
            9,
        ], fn($v) => $v !== null));

        // 1つずつ UPDATE 試行
        foreach ($candidates as $cand) {
            try {
                $day->status = $cand;
                $day->save();
                return; // 成功
            } catch (QueryException $e) {
                // 次の候補へ
            }
        }

        throw new \RuntimeException('退勤済みのDB値を特定できませんでした。ENUMの実値を教えてください。');
    }

    /** 未終了休憩をすべて終了（UIが休憩中判定しないようにする） */
    protected function closeAllOpenBreaks(AttendanceDay $day, string $end = '17:59:00'): void
    {
        BreakPeriod::where('attendance_day_id', $day->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => Carbon::parse($day->work_date->format('Y-m-d') . ' ' . $end, $this->tz())]);
    }

    /** 退勤済みの可能性がある status 値を保存できるまで総当りし、成功した値を返す */
    protected function setStatusCheckedOutByBruteForce(AttendanceDay $day)
    {
        // モデル定数があれば先頭に
        $const = null;
        $cls = \App\Models\AttendanceDay::class;
        $full = $cls . '::STATUS_CHECKED_OUT';
        if (defined($full)) $const = constant($full);

        $candidates = array_values(array_filter([
            $const,
            // よくある表記ゆれ（必要ならここに1行足せばOK）
            'checked_out',
            'checkedout',
            'checkout',
            'left',
            'out',
            'finished',
            'done',
            'retired',
            '退勤済',
            // 数値候補（tinyint想定）
            3,
            9,
        ], fn($v) => $v !== null));

        foreach ($candidates as $cand) {
            try {
                $day->status = $cand;
                $day->save();
                return $cand; // 成功した実値を返す
            } catch (QueryException $e) {
                // 次へ
            }
        }
        throw new \RuntimeException('退勤済みに相当するDB実値を特定できませんでした。ENUMの実値を1つ教えてください。');
    }

    protected function seedDay(User $u, string $date): void
    {
        AttendanceDay::create(['user_id' => $u->id, 'work_date' => $date]);
    }
}
