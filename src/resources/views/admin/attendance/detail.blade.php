@extends('layouts.app')

@section('title', '勤怠詳細（管理者）')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/detail.css') }}">
@endsection

@section('content')

@php
  $mode       = $mode ?? 'normal';     // 'pending' | 'approved' | 'normal'
  $isEditable = $isEditable ?? false;  // Controller 側で制御
  // 承認待ち or 承認済み、または編集不可指定のときは読み取り専用
  $isReadOnly = ($mode !== 'normal') || (!$isEditable);

  // "--:--" / null / '' を空文字へ正規化
  $hm = function($v){
      if ($v === null) return '';
      if (is_string($v)) {
          $t = trim($v);
          return ($t === '' || $t === '--:--') ? '' : $t;
      }
      return $v;
  };

  // 出勤・退勤（old() を通した後に正規化）
  $clockInVal  = $hm(old('clock_in',  $record['clock_in']  ?? ''));
  $clockOutVal = $hm(old('clock_out', $record['clock_out'] ?? ''));

  // 休憩：行ごとに old() → 正規化
  $origBreaks = $record['breaks'] ?? [];
  $breaks = [];
  foreach ($origBreaks as $i => $b) {
      $breaks[] = [
          'start' => $hm(old("breaks.$i.start", $b['start'] ?? '')),
          'end'   => $hm(old("breaks.$i.end",   $b['end']   ?? '')),
      ];
  }

  // 空行判定（正規化後）
  $isEmpty = fn($b) => ($b['start'] === '' && $b['end'] === '');

  // 表示行の最終整形：
  // - pending のときは空行を除去（見やすさ）
  // - normal/approved のときは末尾に空行が無ければ 1 行追加（編集しやすさ）
  if ($mode === 'pending') {
      $breaks = array_values(array_filter($breaks, fn($b) => ! $isEmpty($b)));
  } else {
      if (empty($breaks) || ! $isEmpty($breaks[count($breaks)-1])) {
          $breaks[] = ['start' => '', 'end' => ''];
      }
  }
@endphp

<div class="att-detail">
  <div class="att-detail__inner">

    <h1 class="page-title">勤怠詳細</h1>

    <form method="POST"
          action="{{ route('admin.attendance.updateByUserDate', ['user' => $user->id]) }}?date={{ $date->toDateString() }}">
      @csrf
      <input type="hidden" name="date" value="{{ $date->toDateString() }}">

      <div class="detail-card {{ $mode === 'pending' ? 'is-pending' : '' }}">
        <table class="detail-table">
          <tbody>
            {{-- 名前 --}}
            <tr>
              <th>名前</th>
              <td colspan="3" class="cell--center">{{ $record['name'] ?? '' }}</td>
            </tr>

            {{-- 日付 --}}
            <tr>
              <th>日付</th>
              <td class="cell--center cell--ym">{{ $date->isoFormat('YYYY年') }}</td>
              <td class="cell--center cell--md" colspan="2">{{ $date->isoFormat('M月D日') }}</td>
            </tr>

            {{-- 出勤・退勤（未入力は空欄表示） --}}
            <tr>
              <th>出勤・退勤</th>
              <td class="cell--inputs">
                <input
                  class="chip-input time-input {{ $clockInVal === '' ? 'is-empty' : '' }}"
                  type="time" name="clock_in"
                  value="{{ $clockInVal }}" {{ $isReadOnly ? 'readonly' : '' }}>
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <input
                  class="chip-input time-input {{ $clockOutVal === '' ? 'is-empty' : '' }}"
                  type="time" name="clock_out"
                  value="{{ $clockOutVal }}" {{ $isReadOnly ? 'readonly' : '' }}>
              </td>
            </tr>
            @error('clock_pair')  <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror

            {{-- 休憩（正規化済み $breaks をそのまま使う） --}}
            @foreach ($breaks as $idx => $b)
              <tr>
                <th>休憩{{ $idx + 1 }}</th>
                <td class="cell--inputs">
                  <input
                    class="chip-input time-input {{ ($b['start'] ?? '') === '' ? 'is-empty' : '' }}"
                    type="time" name="breaks[{{ $idx }}][start]"
                    value="{{ $b['start'] }}" {{ $isReadOnly ? 'readonly' : '' }}>
                </td>
                <td class="cell--tilde">〜</td>
                <td class="cell--inputs">
                  <input
                    class="chip-input time-input {{ ($b['end'] ?? '') === '' ? 'is-empty' : '' }}"
                    type="time" name="breaks[{{ $idx }}][end]"
                    value="{{ $b['end'] }}" {{ $isReadOnly ? 'readonly' : '' }}>
                </td>
              </tr>
            @endforeach
            @error('breaks_range') <tr><td colspan="4" class="cell--error">{{ $message }}</td></tr> @enderror

            {{-- 備考 --}}
            <tr>
              <th>備考</th>
              <td colspan="3" class="cell--inputs">
                <textarea class="note-box" name="note" rows="2" {{ $isReadOnly ? 'readonly' : '' }}>{{ old('note', $record['note'] ?? '') }}</textarea>
              </td>
            </tr>
            @error('note') <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror
          </tbody>
        </table>
      </div>

      {{-- ボタン：編集可能なときのみ（= normal かつ isEditable=true） --}}
      <div class="detail-actions">
        @unless($isReadOnly)
          <button type="submit" class="btn-primary">修正</button>
        @endunless
      </div>

      {{-- 注意文（状態別） --}}
      @if ($mode === 'pending')
        <p class="pending-note">※承認待ちのため修正はできません。</p>
      @endif
    </form>

  </div>
</div>

{{-- 入力の有無で is-empty を動的付与（見た目を“空欄”に保つ） --}}
<script>
document.addEventListener('input', (e) => {
  if (e.target.matches('input[type="time"].time-input')) {
    e.target.classList.toggle('is-empty', !e.target.value);
  }
});
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type="time"].time-input').forEach(el => {
    el.classList.toggle('is-empty', !el.value);
  });
});
</script>

@endsection
