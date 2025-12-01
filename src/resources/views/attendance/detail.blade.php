@extends('layouts.app')

@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance/detail.css') }}">
@endsection

@section('content')

@php
  $isPending = $isPending ?? false;

  // "--:--" / null / 空 をすべて空文字に正規化
  $hm = function($v){
      if ($v === null) return '';
      if (is_string($v)) {
          $t = trim($v);
          return ($t === '' || $t === '--:--') ? '' : $t;
      }
      return $v;
  };

  // 出勤・退勤（old() を通してから正規化）
  $clockInVal  = $hm(old('clock_in',  $record['clock_in']  ?? ''));
  $clockOutVal = $hm(old('clock_out', $record['clock_out'] ?? ''));

  // 休憩（各行の start/end を old()→正規化）
  $origBreaks = $record['breaks'] ?? [];
  $breaks = [];
  foreach ($origBreaks as $i => $b) {
      $breaks[] = [
          'start' => $hm(old("breaks.$i.start", $b['start'] ?? '')),
          'end'   => $hm(old("breaks.$i.end",   $b['end']   ?? '')),
      ];
  }

  // 空行かどうか（正規化後の空文字で判定）
  $isEmptyBreak = fn($b) => ($b['start'] === '' && $b['end'] === '');

  // 承認待ちのときだけ空行を除去（見やすさのため）
  if ($isPending) {
      $breaks = array_values(array_filter($breaks, fn($b) => ! $isEmptyBreak($b)));
  }
@endphp

<div class="att-detail">
  <div class="att-detail__inner">

    <h1 class="page-title">勤怠詳細</h1>

    {{-- ステータス / エラーメッセージ --}}
    @if (session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('attendance.request', ['date' => $date->toDateString()]) }}">
      @csrf
      <input type="hidden" name="date" value="{{ $date->toDateString() }}">

      <div class="detail-card {{ $isPending ? 'is-pending' : '' }}">
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

            {{-- 出勤・退勤（未入力は視覚的に空欄） --}}
            <tr>
              <th>出勤・退勤</th>
              <td class="cell--inputs">
                <input
                  class="chip-input time-input {{ $clockInVal === '' ? 'is-empty' : '' }}"
                  type="time" name="clock_in"
                  value="{{ $clockInVal }}" {{ $isPending ? 'readonly' : '' }}>
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <input
                  class="chip-input time-input {{ $clockOutVal === '' ? 'is-empty' : '' }}"
                  type="time" name="clock_out"
                  value="{{ $clockOutVal }}" {{ $isPending ? 'readonly' : '' }}>
              </td>
            </tr>
            @error('clock_pair')  <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror

            {{-- 休憩（ここでは $breaks を再定義しない！） --}}
            @foreach ($breaks as $idx => $b)
              <tr>
                <th>休憩{{ $idx + 1 }}</th>
                <td class="cell--inputs">
                  <input
                    class="chip-input time-input {{ ($b['start'] ?? '') === '' ? 'is-empty' : '' }}"
                    type="time" name="breaks[{{ $idx }}][start]"
                    value="{{ $b['start'] }}" {{ $isPending ? 'readonly' : '' }}>
                </td>
                <td class="cell--tilde">〜</td>
                <td class="cell--inputs">
                  <input
                    class="chip-input time-input {{ ($b['end'] ?? '') === '' ? 'is-empty' : '' }}"
                    type="time" name="breaks[{{ $idx }}][end]"
                    value="{{ $b['end'] }}" {{ $isPending ? 'readonly' : '' }}>
                </td>
              </tr>
            @endforeach

            @error('breaks_range') <tr><td colspan="4" class="cell--error">{{ $message }}</td></tr> @enderror

            {{-- 備考 --}}
            <tr>
              <th>備考</th>
              <td colspan="3" class="cell--inputs">
                <textarea class="note-box" name="note" rows="2" {{ $isPending ? 'readonly' : '' }}>{{ old('note', $record['note'] ?? '') }}</textarea>
              </td>
            </tr>
            @error('note') <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror
          </tbody>
        </table>
      </div>

      {{-- 修正ボタン --}}
      <div class="detail-actions">
        @unless($isPending)
          <button type="submit" class="btn-primary">修正</button>
        @endunless
      </div>

      {{-- 承認待ちメッセージ --}}
      @if ($isPending)
        <p class="pending-note">※承認待ちのため修正はできません。</p>
      @endif
    </form>

  </div>
</div>

{{-- 入力後に is-empty クラスを更新（任意） --}}
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
