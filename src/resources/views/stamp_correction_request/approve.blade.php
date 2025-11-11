@php
  $tz   = config('app.timezone', 'Asia/Tokyo');
  $date = isset($date) ? $date : (!empty($req->target_at) ? \Carbon\Carbon::parse($req->target_at, $tz) : null);

  // "2025-10-12 09:00:00" 等を "09:00" 表示にする小さなヘルパ
  $hm = function ($v) use ($tz) {
      if (empty($v)) return '-';
      try {
        return \Carbon\Carbon::parse($v, $tz)->format('H:i');
      } catch (\Throwable $e) {
        return $v; // 既に "09:00" 形式ならそのまま出す
      }
  };
@endphp

@extends('layouts.app')

@section('title', '修正申請承認')

@section('css')
<link rel="stylesheet" href="{{ asset('css/stamp_correction_request/approve.css') }}">
@endsection

@section('content')
@php
  /**
   * 受け取り想定:
   * $date : \Carbon\Carbon (対象日)
   * $record : [
   *   'user_id'=>1,'name'=>'西 伶奈',
   *   'clock_in'=>'09:00','clock_out'=>'18:00',
   *   'break1_start'=>'12:00','break1_end'=>'13:00',
   *   'break2_start'=>null,'break2_end'=>null,
   *   'note'=>'電車遅延のため'
   * ]
   * $requestId : int  … この修正申請のID
   * $status    : 'pending' | 'approved'
   */
  $approved = (session('approved') === true) || ($status ?? 'pending') === 'approved';
@endphp

<div class="approve-page">
  <div class="approve-page__inner">

    <h1 class="page-title">勤怠詳細</h1>
    
    @php
      $fmtDateY = optional($date)->isoFormat('YYYY年') ?? '-';
      $fmtDateMD = optional($date)->isoFormat('M月D日') ?? '-';
    @endphp

    <div class="detail-card">
      <table class="detail-table">
        <tbody>
          <tr>
            <th>名前</th>
            <td colspan="3" class="cell--center">
              {{ optional($req->user)->name ?? ($req->name ?? '-') }}
            </td>
          </tr>

          <tr>
            <th>日付</th>
            <td class="cell--center cell--ym">{{ $fmtDateY }}</td>
            <td class="cell--center cell--md" colspan="2">{{ $fmtDateMD }}</td>
          </tr>

          <tr>
            <th>出勤・退勤</th>
            <td class="cell--inputs"><span class="chip">{{ $record['clock_in']  ?? '-' }}</span></td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs"><span class="chip">{{ $record['clock_out'] ?? '-' }}</span></td>
          </tr>

          <tr>
            <th>休憩</th>
            <td class="cell--inputs"><span class="chip">{{ $record['break1_start'] ?? '-' }}</span></td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs"><span class="chip">{{ $record['break1_end'] ?? '-' }}</span></td>
          </tr>

          <tr>
            <th>休憩2</th>
            <td class="cell--inputs"><span class="chip">{{ $record['break2_start'] ?? '-' }}</span></td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs"><span class="chip">{{ $record['break2_end'] ?? '-' }}</span></td>
          </tr>

          <tr>
            <th>備考</th>
            <td colspan="3" class="cell--inputs">
              <div class="note-box">{{ $record['note'] ?? '' }}</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    {{-- 承認アクション --}}
    @php $approved = ($req->status === 'approved'); @endphp

    @if ($approved)
      <div class="approve-actions">
        <span class="badge-approved">承認済み</span>
      </div>
    @else
      <div class="approve-actions">
        <form action="{{ url('/admin/stamp_correction_request/approve/'.$req->id) }}"
              method="POST" novalidate>
          @csrf
          <button type="submit" class="btn-primary">承認</button>
        </form>
      </div>
    @endif
  </div>
</div>

@endsection
