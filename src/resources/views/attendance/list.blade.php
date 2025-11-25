@extends('layouts.app')

@section('title', '勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance/list.css') }}">
@endsection

@section('content')
@php
  /**
   * 受け取り想定
   * $month : \Carbon\Carbon   … 対象月の1日
   * $days  : array            … その月の日別データ
   *   例: [
   *     ['date'=>'2023-06-01','clock_in'=>'09:00','clock_out'=>'18:00','break_total'=>'1:00','work_total'=>'8:00'],
   *     ...
   *   ]
   */
  $prevMonth = $month->copy()->subMonthNoOverflow();
  $nextMonth = $month->copy()->addMonthNoOverflow();
@endphp

<div class="user-att">
  <div class="user-att__inner">

    <h1 class="page-title">勤怠一覧</h1>

    <!-- 月ナビ -->
    <div class="month-nav">
      <a class="month-nav__btn"
         href="{{ route('attendance.list', ['month' => $prevMonth->format('Y-m')]) }}">
        <span class="month-nav__arrow">&#8592;</span> 前月
      </a>

      <form id="monthNavForm" class="month-nav__center" action="{{ route('attendance.list') }}" method="get">
        {{-- ▼ カレンダーアイコン --}}
        <svg class="month-nav__icon" viewBox="0 0 24 24" aria-hidden="true">
          <!-- 外枠 -->
          <rect x="3" y="4.5" width="18" height="16" rx="2.5" ry="2.5"
                fill="none" stroke="currentColor" stroke-width="1.8"/>
          <!-- 綴じ具 -->
          <line x1="8" y1="4.5" x2="8" y2="2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <line x1="16" y1="4.5" x2="16" y2="2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <!-- 仕切り線 -->
          <line x1="3" y1="8.5" x2="21" y2="8.5" stroke="currentColor" stroke-width="1.6"/>
          <!-- マス目（3×2） -->
          <rect x="5.5"  y="10.5" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.2"/>
          <rect x="10.5" y="10.5" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.2"/>
          <rect x="15.5" y="10.5" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.2"/>
          <rect x="5.5"  y="14.9" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.2"/>
          <rect x="10.5" y="14.9" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.2"/>
          <rect x="15.5" y="14.9" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.2"/>
          <!-- チェック（2つ） -->
          <path d="M11.2 12.2l1.0 1.0 2.0-2.0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M6.2 16.6l1.0 1.0 2.0-2.0"  fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      
        <span id="monthDisplay" class="month-nav__display">{{ $month->isoformat('YYYY年M月') }}</span>
      
        <input id="monthPicker" class="month-nav__input" type="month" name="month"
               value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
      </form>
      
      <script>
      (() => {
        const form  = document.getElementById('monthNavForm');
        const input = document.getElementById('monthPicker');
        form.addEventListener('click', (e) => {
          if (e.target.closest('.month-nav__icon') || e.target.closest('.month-nav__display')) {
            if (typeof input.showPicker === 'function') input.showPicker();
            else input.focus();
          }
        });
      })();
      </script>

      <a class="month-nav__btn month-nav__btn--right"
         href="{{ route('attendance.list', ['month' => $nextMonth->format('Y-m')]) }}">
        翌月 <span class="month-nav__arrow">&#8594;</span>
      </a>
    </div>

    <!-- テーブル -->
    <div class="att-table__wrap">
      <table class="att-table">
        <thead>
          <tr>
            <th>日付</th>
            <th>出勤</th>
            <th>退勤</th>
            <th>休憩</th>
            <th>合計</th>
            <th>詳細</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($days as $d)
            @php $dCarbon = \Carbon\Carbon::parse($d['date']); @endphp
            <tr>
              <td class="cell--date">{{ $dCarbon->isoFormat('MM/DD(ddd)') }}</td>
              <td class="cell--time">{{ $d['clock_in']   ?? '' }}</td>
              <td class="cell--time">{{ $d['clock_out']  ?? '' }}</td>
              <td class="cell--time">{{ $d['break_text'] ?? ($d['break_total'] ?? '') }}</td>
              <td class="cell--time">{{ $d['work_total'] ?? '' }}</td>
              <td class="cell--link">
                <a class="detail-link"
                   href="{{ route('attendance.detail', ['date' => $dCarbon->toDateString()]) }}">
                  詳細
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

  </div>
</div>
@endsection
