<?php

namespace Tests\Feature\Acceptance;

use Carbon\Carbon;
use Tests\Feature\Acceptance\_helpers\FeatureTestCase;

class Case07_BreakFeatureTest extends FeatureTestCase
{
    private string $ROUTE_PUNCH = '/attendance/punch';

    private function act(string $action)
    {
        // CSRFはテスト環境では不要。actionだけ渡せばOK
        return $this->post($this->ROUTE_PUNCH, ['action' => $action]);
    }

    /** ①休憩ボタンが正しく機能する */
    public function test_break_in_button_and_status_changes_to_breaking()
    {
        $u = $this->makeUser();
        $today = Carbon::now('Asia/Tokyo')->toDateString();
        $this->seedAttendance($u, $today, '09:00', null);

        $this->actingAs($u, 'web');

        // 休憩入り
        $this->act('break-start')->assertRedirect();

        // ステータスが休憩中表示になる
        $this->get($this->ROUTE_STAMP)->assertStatus(200)->assertSee('休憩中');
    }

    /** ②休憩は一日に何回でもできる */
    public function test_break_can_happen_many_times_in_a_day()
    {
        $u = $this->makeUser();
        $today = Carbon::now('Asia/Tokyo')->toDateString();
        $this->seedAttendance($u, $today, '09:00', null);

        $this->actingAs($u, 'web');

        // 1回目 入→戻
        $this->act('break-start')->assertRedirect();
        $this->act('break-end')->assertRedirect();

        // 2回目 入（何度でも入れる想定）
        $this->act('break-start')->assertRedirect();

        // 休憩中であること
        $this->get($this->ROUTE_STAMP)->assertStatus(200)->assertSee('休憩中');
    }

    /** ③休憩戻ボタンが正しく機能する */
    public function test_break_back_button_and_status_returns_to_working()
    {
        $u = $this->makeUser();
        $today = Carbon::now('Asia/Tokyo')->toDateString();
        $this->seedAttendance($u, $today, '09:00', null);

        $this->actingAs($u, 'web');

        // 入→戻
        $this->act('break-start')->assertRedirect();
        $this->act('break-end')->assertRedirect();

        // 出勤中に戻っている
        $this->get($this->ROUTE_STAMP)->assertStatus(200)->assertSee('出勤中');
    }

    /** ④休憩戻は一日に何回でもできる */
    public function test_break_back_can_happen_many_times_in_a_day()
    {
        $u = $this->makeUser();
        $today = Carbon::now('Asia/Tokyo')->toDateString();
        $this->seedAttendance($u, $today, '09:00', null);

        $this->actingAs($u, 'web');

        // 1回目 入→戻
        $this->act('break-start')->assertRedirect();
        $this->act('break-end')->assertRedirect();

        // 2回目 入→戻
        $this->act('break-start')->assertRedirect();
        $this->act('break-end')->assertRedirect();

        // 最終的に出勤中、かつ「休憩入」操作が再び可能な状態（= 画面に「休憩」系文言がある）
        $this->get($this->ROUTE_STAMP)
            ->assertStatus(200)
            ->assertSee('出勤中')
            ->assertSee('休憩'); // ボタンラベルに依存しないゆるい確認
    }

    /** ⑤休憩時刻が勤怠一覧画面で確認できる */
    public function test_break_times_appear_on_attendance_list()
    {
        $u = $this->makeUser();
        $today = Carbon::now('Asia/Tokyo')->toDateString();
        $this->seedAttendance($u, $today, '09:00', null);

        $this->actingAs($u, 'web');

        // 入→戻→退勤まで行ってから一覧を確認（あなたの実装は退勤後に休憩表示が確定する想定）
        $this->act('break-start')->assertRedirect();
        $this->act('break-end')->assertRedirect();
        $this->act('clock-out')->assertRedirect();

        $html = $this->get($this->ROUTE_USER_ATT_LIST)->assertStatus(200)->getContent();

        // "HH:MM - HH:MM" / "HH:MM〜HH:MM" / "HH:MM－HH:MM" のいずれか
        $this->assertMatchesRegularExpression('/\b\d{2}:\d{2}\s*[-〜－]\s*\d{2}:\d{2}\b/u', $html);
    }
}
