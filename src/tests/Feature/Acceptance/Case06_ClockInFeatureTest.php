<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class Case06_ClockInFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $ROUTE_STAMP = '/attendance/stamp';
    private string $ROUTE_PUNCH = '/attendance/punch'; // ← 実装に合わせる

    /** ①出勤ボタンが正しく機能する */
    public function test_clock_in_button_and_status()
    {
        /** @var \App\Models\User $u */
        $u = User::factory()->create();
        $this->actingAs($u, 'web');

        // 出勤前の画面に「出勤ボタン」がある（value="clock-in" で判定）
        $this->get($this->ROUTE_STAMP)->assertSee('value="clock-in"', false);

        // 出勤打刻（実装に合わせて /attendance/punch へ action=clock-in）
        $this->post($this->ROUTE_PUNCH, ['action' => 'clock-in'])->assertRedirect();

        // 出勤後は「出勤中」バッジが出て、出勤ボタンは消える
        $this->get($this->ROUTE_STAMP)
            ->assertSee('出勤中')
            ->assertDontSee('value="clock-in"', false);
    }

    /** ②出勤は一日一回のみできる */
    public function test_clock_in_only_once_a_day()
    {
        $u   = User::factory()->create();
        $tz  = config('app.timezone', 'Asia/Tokyo');
        $day = Carbon::now($tz)->toDateString();

        // 既に当日の出勤がある状態を作る
        AttendanceDay::factory()->create([
            'user_id'     => $u->id,
            'work_date'   => $day . ' 00:00:00',
            'clock_in_at' => $day . ' 09:00:00',
            'clock_out_at' => null,
        ]);

        /** @var \App\Models\User $u */
        $this->actingAs($u, 'web');

        // 出勤済み画面（退勤前）では「出勤ボタン」が表示されないことを
        // 文言「出勤」ではなく実際のボタン value 属性で検証する
        $this->get($this->ROUTE_STAMP)
            ->assertDontSee('value="clock-in"', false);
    }

    /** ③出勤時刻が勤怠一覧画面で確認できる */
    public function test_clock_in_time_appears_on_list()
    {
        $u   = User::factory()->create();
        $tz  = config('app.timezone', 'Asia/Tokyo');
        $day = Carbon::now($tz)->toDateString();

        AttendanceDay::factory()->create([
            'user_id'     => $u->id,
            'work_date'   => $day . ' 00:00:00',
            'clock_in_at' => $day . ' 09:00:00',
            'clock_out_at' => null,
        ]);

        /** @var \App\Models\User $u */
        $this->actingAs($u, 'web');

        // 一覧に 09:00 が出る（既存実装に合わせたまま）
        $this->get('/attendance/list')->assertSee('09:00');
    }
}
