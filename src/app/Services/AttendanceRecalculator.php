<?php

namespace App\Services;

use App\Models\AttendanceDay;
use Carbon\CarbonImmutable;

class AttendanceRecalculator
{
  /**
   * 勤怠1日の合計休憩分・合計労働分を再計算して保存する
   * - 休憩は started_at & ended_at が両方あるもののみ集計
   * - 勤務時間 = (clock_out - clock_in) - 総休憩
   * - どちらかが欠ける場合は null にする
   */
  public function recalc(AttendanceDay $day): AttendanceDay
  {
    // タイムゾーン
    $tz = config('app.timezone', 'Asia/Tokyo');

    // 1) 休憩合計
    $totalBreak = 0;
    foreach ($day->breaks as $br) {
      if ($br->started_at && $br->ended_at) {
        $start = CarbonImmutable::parse($br->started_at)->timezone($tz);
        $end   = CarbonImmutable::parse($br->ended_at)->timezone($tz);
        // マイナス防止
        if ($end->greaterThan($start)) {
          $totalBreak += $end->diffInMinutes($start);
        }
      }
    }

    // 2) 労働合計
    $totalWork = null;
    if ($day->clock_in_at && $day->clock_out_at) {
      $in  = CarbonImmutable::parse($day->clock_in_at)->timezone($tz);
      $out = CarbonImmutable::parse($day->clock_out_at)->timezone($tz);
      if ($out->greaterThan($in)) {
        $gross = $out->diffInMinutes($in);
        $net   = max(0, $gross - $totalBreak);
        $totalWork = $net;
      }
    }

    $day->total_break_minutes = $totalBreak ?: null; // 0 なら null でも可。要件次第で 0 にしてもOK
    $day->total_work_minutes  = $totalWork;

    $day->save();

    return $day->refresh();
  }
}
