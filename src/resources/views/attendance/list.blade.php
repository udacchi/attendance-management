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

<div class="user-attendance">
  <div class="user-attendance__inner">

    <h1 class="page-title">勤怠一覧</h1>

    <!-- 月ナビ -->
    <div class="month-nav">
      <a class="month-nav__btn"
         href="{{ route('attendance.list', ['month' => $prevMonth->format('Y-m')]) }}">
        <span class="month-nav__arrow">&#8592;</span> 前月
      </a>

      <form class="month-nav__center" action="{{ route('attendance.list') }}" method="get">
        <i class="month-nav__icon fa-regular fa-calendar"></i>
        <input
          class="month-nav__input"
          type="month"
          name="month"
          value="{{ $month->format('Y-m') }}"
          onchange="this.form.submit()"
        >
      </form>

      <a class="month-nav__btn month-nav__btn--right"
         href="{{ route('attendance.list', ['month' => $nextMonth->format('Y-m')]) }}">
        翌月 <span class="month-nav__arrow">&#8594;</span>
      </a>
    </div>

    <!-- テーブル -->
    <div class="attendance-table__wrap">
      <table class="attendance-table">
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
              <td class="cell--time">{{ $d['clock_in']   ?? '-' }}</td>
              <td class="cell--time">{{ $d['clock_out']  ?? '-' }}</td>
              <td class="cell--time">{{ $d['break_total']?? '-' }}</td>
              <td class="cell--time">{{ $d['work_total'] ?? '-' }}</td>
              <td class="cell--link">
                <a class="detail-link"
                   href="{{ route('attendance.show', ['date' => $dCarbon->toDateString()]) }}">
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
