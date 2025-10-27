@extends('layouts.app')

@section('title', 'スタッフ別勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/staff.css') }}">
@endsection

@section('content')
@php
  /**
   * 受け取り想定
   * $user : App\Models\User（少なくとも ->id, ->name）
   * $month : \Carbon\Carbon （対象月の1日を指す想定）
   * $days  : [
   *   ['date'=>'2023-06-01','clock_in'=>'09:00','clock_out'=>'18:00','break_total'=>'1:00','work_total'=>'8:00'],
   *   ...
   * ]
   */
  $prevMonth = $month->copy()->subMonthNoOverflow();
  $nextMonth = $month->copy()->addMonthNoOverflow();
@endphp

<div class="admin-staff-attendance">
  <div class="admin-staff-attendance__inner">

    <h1 class="page-title">{{ $user->name }}さんの勤怠</h1>

    <!-- 月ナビ -->
    <div class="month-nav">
      <a class="month-nav__btn" href="{{ route('admin.attendance.staff', ['id' => $user->id, 'month' => $prevMonth->format('Y-m')]) }}">
        <span class="month-nav__arrow">&#8592;</span> 前月
      </a>

      <form class="month-nav__center" action="{{ route('admin.attendance.staff', ['id' => $user->id]) }}" method="get">
        <i class="month-nav__icon fa-regular fa-calendar"></i>
        <input
          class="month-nav__input"
          type="month"
          name="month"
          value="{{ $month->format('Y-m') }}"
          onchange="this.form.submit()"
        >
      </form>

      <a class="month-nav__btn month-nav__btn--right" href="{{ route('admin.attendance.staff', ['id' => $user->id, 'month' => $nextMonth->format('Y-m')]) }}">
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
                   href="{{ route('admin.attendance.detail', ['id' => $user->id, 'date' => $dCarbon->toDateString()]) }}">
                  詳細
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="cell--empty">この月のデータはありません</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <!-- CSV出力 -->
    <div class="page-actions">
      <a class="btn-primary"
         href="{{ route('admin.attendance.staff.csv', ['id' => $user->id, 'month' => $month->format('Y-m')]) }}">
        CSV出力
      </a>
    </div>

  </div>
</div>
@endsection
