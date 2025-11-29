@extends('layouts.app')

@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance/detail.css') }}">
@endsection

@section('content')
@php
  // コントローラから渡ってくる想定:
  // $date   : Carbon (対象日)
  // $record : [
  //   'user_id'   => 認証ユーザーID,
  //   'name'      => 氏名,
  //   'clock_in'  => 'HH:MM' or '',
  //   'clock_out' => 'HH:MM' or '',
  //   'note'      => string,
  //   'breaks'    => [
  //      ['start' => 'HH:MM', 'end' => 'HH:MM'],
  //      ...,
  //      ['start' => '', 'end' => ''], // 追加1行分
  //   ],
  // ]
  // $isPending : bool（承認待ちの申請があるか）
  $isPending = $isPending ?? false;
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

      <div class="detail-card {{ ($isPending ?? false) ? 'is-pending' : '' }}">
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
      
            {{-- 出勤・退勤（← 承認待ちでも送信したい将来に備えるなら disabled→readonly に） --}}
            <tr>
              <th>出勤・退勤</th>
              <td class="cell--inputs">
                <input class="chip-input" type="time" name="clock_in"
                       value="{{ old('clock_in', $record['clock_in'] ?? '') }}" {{ $isPending ? 'readonly' : '' }}>
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <input class="chip-input" type="time" name="clock_out"
                       value="{{ old('clock_out', $record['clock_out'] ?? '') }}" {{ $isPending ? 'readonly' : '' }}>
              </td>
            </tr>
            @error('clock_pair')  <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror
      
            {{-- 休憩 --}}
            @php
              $breaks = $record['breaks'] ?? [];
              $isEmptyBreak = function($b){
                  $s = trim($b['start'] ?? '');
                  $e = trim($b['end'] ?? '');
                  // '--:--' や null, '' を未入力として扱う
                  return ($s === '' || $s === '--:--') && ($e === '' || $e === '--:--');
              };
            
              // 承認待ちのときだけ空行を除外
              if ($isPending) {
                  $breaks = array_values(array_filter($breaks, fn($b) => ! $isEmptyBreak($b)));
              }
            @endphp

            @foreach ($breaks as $idx => $b)
              <tr>
                <th>休憩{{ $idx + 1 }}</th>
                <td class="cell--inputs">
                  <input class="chip-input" type="time" name="breaks[{{ $idx }}][start]"
                         value="{{ old("breaks.$idx.start", $b['start'] ?? '') }}" {{ $isPending ? 'readonly' : '' }}>
                </td>
                <td class="cell--tilde">〜</td>
                <td class="cell--inputs">
                  <input class="chip-input" type="time" name="breaks[{{ $idx }}][end]"
                         value="{{ old("breaks.$idx.end", $b['end'] ?? '') }}" {{ $isPending ? 'readonly' : '' }}>
                </td>
              </tr>
            @endforeach

            @error('breaks_range') <tr><td colspan="4" class="cell--error">{{ $message }}</td></tr> @enderror
      
            {{-- 備考（インデント空白を入れない） --}}
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
        @if (! $isPending)
          <button type="submit" class="btn-primary">修正</button>
        @endif
      </div>

      @if ($isPending)
          {{--承認待ちメッセージ --}}
        <p class="pending-note">※承認待ちのため修正はできません。</p>
      @endif
    </form>

  </div>
</div>
@endsection
