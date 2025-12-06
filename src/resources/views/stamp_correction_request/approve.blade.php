@extends('layouts.app')

@section('title', '修正申請承認')

@section('css')
<link rel="stylesheet" href="{{ asset('css/stamp_correction_request/approve.css') }}">
@endsection

@section('content')
@php
  $tz        = config('app.timezone', 'Asia/Tokyo');
  $isPending = $isPending ?? (($req->status ?? null) === 'pending');

  // --:-- を空文字に正規化して表示専用の配列を用意
  $normHM = function ($v) {
      $v = (string)($v ?? '');
      return $v === '--:--' ? '' : $v;
  };

  $clockIn  = $normHM($record['clock_in']  ?? '');
  $clockOut = $normHM($record['clock_out'] ?? '');

  // 元の breaks（空行は来ない想定）を正規化
  $breaks = [];
  foreach (($record['breaks'] ?? []) as $b) {
      $breaks[] = [
          'start' => $normHM($b['start'] ?? ''),
          'end'   => $normHM($b['end']   ?? ''),
      ];
  }

  // ★ ここがポイント：最低 2 行は表示（不足分は空行でパディング）
  $minRows = 2;
  for ($i = count($breaks); $i < $minRows; $i++) {
      $breaks[] = ['start' => '', 'end' => ''];
  }
@endphp


<div class="approve-page">
  <div class="approve-page__inner">

    <h1 class="page-title">勤怠詳細</h1>

    <div class="approve-card">
      <table class="approve-table">
        <tbody>
          {{-- 名前 --}}
          <tr>
            <th>名前</th>
            <td colspan="3" class="cell--center">{{ $record['name'] ?? '' }}</td>
          </tr>

          {{-- 日付 --}}
          <tr>
            <th>日付</th>
            <td class="cell--center cell--ym">{{ optional($date)->isoFormat('YYYY年') }}</td>
            <td class="cell--center cell--md" colspan="2">{{ optional($date)->isoFormat('M月D日') }}</td>
          </tr>

          {{-- 出勤・退勤（入力欄は使わず、テキストで表示。未入力は空白） --}}
          <tr>
            <th>出勤・退勤</th>
            <td class="cell--inputs">
              <span class="chip-text">{{ $clockIn }}</span>
            </td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs">
              <span class="chip-text">{{ $clockOut }}</span>
            </td>
          </tr>

          {{-- 休憩（未入力は空白表示。--:-- は空に変換済み） --}}
          {{-- 休憩（未入力は空白表示。--:-- は空に変換済み） --}}
          @foreach ($breaks as $i => $b)
            @php
              $label = $i === 0 ? '休憩' : '休憩' . ($i + 1);
            @endphp
            <tr>
              <th class="cell">{{ $label }}</th>
              <td class="cell--inputs">
                <span class="chip-text">{{ $b['start'] }}</span>
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <span class="chip-text">{{ $b['end'] }}</span>
              </td>
            </tr>
          @endforeach

          {{-- 備考（表示専用） --}}
          <tr>
            <th>備考</th>
            <td colspan="3" class="cell--inputs">
              <div class="note-box note-box--static">
                {!! nl2br(e(($record['note'] ?? '') !== '' ? $record['note'] : '（なし）')) !!}
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="approve-actions">
      @if (($req->status ?? null) === 'approved')
        <span class="badge-approved">承認済み</span>
      @else
        <form action="{{ route('stamp_correction_request.approve.store', ['attendance_correct_request_id' => $req->id]) }}"
              method="POST" novalidate>
          @csrf
          <button type="submit" class="btn-primary">承認</button>
        </form>
      @endif
    </div>

  </div>
</div>
@endsection
