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
        <svg class="month-nav__icon" width="28" height="28" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1V3a1 1 0 0 1 1-1Zm12 7H5v10h14V9ZM7 7h10V6H7v1Z" fill="currentColor"/>
        </svg>
      
        <span id="monthDisplay" class="month-nav__display">{{ $month->format('Y/m') }}</span>
      
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
          @forelse ($days as $d)
            @php $dCarbon = \Carbon\Carbon::parse($d['date']); @endphp
            <tr>
              <td class="cell--date">{{ $dCarbon->isoFormat('MM/DD(ddd)') }}</td>
              <td class="cell--time">{{ $d['clock_in']   ?? '' }}</td>
              <td class="cell--time">{{ $d['clock_out']  ?? '' }}</td>
              <td class="cell--time">{{ $d['break_total']?? '' }}</td>
              <td class="cell--time">{{ $d['work_total'] ?? '' }}</td>
              <td class="cell--link">
                <a class="detail-link"
                   href="{{ route('attendance.detail', ['date' => $dCarbon->toDateString()]) }}">
                  詳細
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="cell--empty">この月の勤怠はありません</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

  </div>
</div>
@endsection
