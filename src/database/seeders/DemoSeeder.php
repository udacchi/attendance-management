<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AttendanceDay;
use App\Models\BreakPeriod;
use App\Models\CorrectionRequest;
use App\Models\CorrectionLog;
use App\Services\AttendanceRecalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // タイムゾーン
        date_default_timezone_set(config('app.timezone', 'Asia/Tokyo'));

        // 管理者1名 + 一般ユーザー10名
        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $users = User::factory(10)->create();

        /** @var AttendanceRecalculator $recalc */
        $recalc = App::make(AttendanceRecalculator::class);

        // 直近20日分のダミー勤怠を各ユーザーに付与
        foreach ($users as $user) {
            for ($d = 0; $d < 20; $d++) {
                $date = Carbon::today()->subDays($d)->toDateString();

                /** @var AttendanceDay $day */
                $day = AttendanceDay::factory()->create([
                    'user_id' => $user->id,
                    'work_date' => $date,
                ]);

                // 出勤がある日のみ、休憩を1〜2本生成
                if ($day->clock_in_at && $day->clock_out_at) {
                    $start1 = (clone $day->clock_in_at)->addHours(3)->addMinutes(rand(0, 20));
                    $end1   = (clone $start1)->addMinutes(rand(30, 60));
                    BreakPeriod::create([
                        'attendance_day_id' => $day->id,
                        'started_at' => $start1,
                        'ended_at' => $end1,
                    ]);

                    if (rand(0, 1)) {
                        $start2 = (clone $day->clock_in_at)->addHours(6)->addMinutes(rand(0, 20));
                        $end2   = (clone $start2)->addMinutes(rand(15, 45));
                        BreakPeriod::create([
                            'attendance_day_id' => $day->id,
                            'started_at' => $start2,
                            'ended_at' => $end2,
                        ]);
                    }

                    // 合計の再計算
                    $recalc->recalc($day);
                }

                // たまに修正申請を作る（出退勤・備考のみ）
                if (rand(1, 12) === 1) {
                    $req = CorrectionRequest::factory()
                        ->withProposedTimes()
                        ->create([
                            'attendance_day_id' => $day->id,
                            'requested_by' => $user->id,
                            'status' => 'pending',
                        ]);

                    // さらに稀に即時承認/却下してみる
                    if (rand(1, 6) === 1) {
                        DB::transaction(function () use ($req, $admin, $recalc) {
                            $action = rand(0, 1) ? 'approved' : 'rejected';
                            CorrectionLog::create([
                                'correction_request_id' => $req->id,
                                'admin_id' => $admin->id,
                                'action' => $action,
                                'comment' => $action === 'approved' ? 'OK' : 'No reason',
                            ]);

                            $req->status = $action === 'approved' ? 'approved' : 'rejected';
                            $req->save();

                            if ($action === 'approved') {
                                // 提案値を反映（休憩は対象外）
                                $day = $req->attendanceDay()->lockForUpdate()->first();

                                if ($req->proposed_clock_in_at) {
                                    $day->clock_in_at = $req->proposed_clock_in_at;
                                }
                                if ($req->proposed_clock_out_at) {
                                    $day->clock_out_at = $req->proposed_clock_out_at;
                                }
                                if ($req->proposed_note !== null) {
                                    $day->note = $req->proposed_note;
                                }
                                $day->save();

                                // 再計算
                                $recalc->recalc($day);
                            }
                        });
                    }
                }
            }
        }
    }
}
