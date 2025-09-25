@extends('layouts.admin')

@section('title', '勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/list.css') }}">
@endsection

@section('content')
@php
  /** @var \Carbon\Carbon $date */
  // コントローラから $date(Carbon), $records(配列/コレクション) を受け取る想定
  // $records の各要素: ['user_id'=>1,'name'=>'山田 太郎','clock_in'=>'09:00','clock_out'=>'18:00','break_total'=>'1:00','work_total'=>'8:00']
  $prevDate = $date->copy()->subDay();
  $nextDate = $date->copy()->addDay();
@endphp

<div class="admin-attendance">
  <div class="admin-attendance__inner">

    <h1 class="page-title">
      {{ $date->isoFormat('YYYY年M月D日') }}の勤怠
    </h1>

    <!-- 日付ナビ -->
    <div class="date-nav">
      <a class="date-nav__btn" href="{{ route('admin.attendance.list', ['date' => $prevDate->toDateString()]) }}">
        <span class="date-nav__arrow">&#8592;</span> 前日
      </a>

      <form class="date-nav__center" action="{{ route('admin.attendance.list') }}" method="get">
        <i class="date-nav__icon fa-regular fa-calendar"></i>
        <input class="date-nav__input" type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()">
      </form>

      <a class="date-nav__btn date-nav__btn--right" href="{{ route('admin.attendance.list', ['date' => $nextDate->toDateString()]) }}">
        翌日 <span class="date-nav__arrow">&#8594;</span>
      </a>
    </div>

    <!-- 勤怠テーブル -->
    <div class="attendance-table__wrap">
      <table class="attendance-table">
        <thead>
          <tr>
            <th>名前</th>
            <th>出勤</th>
            <th>退勤</th>
            <th>休憩</th>
            <th>合計</th>
            <th>詳細</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($records as $row)
            <tr>
              <td class="cell--name">{{ $row['name'] }}</td>
              <td class="cell--time">{{ $row['clock_in'] ?? '-' }}</td>
              <td class="cell--time">{{ $row['clock_out'] ?? '-' }}</td>
              <td class="cell--time">{{ $row['break_total'] ?? '-' }}</td>
              <td class="cell--time">{{ $row['work_total'] ?? '-' }}</td>
              <td class="cell--link">
                <a href="{{ route('admin.attendance.show', ['user' => $row['user_id'], 'date' => $date->toDateString()]) }}" class="detail-link">詳細</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="cell--empty">この日のデータはありません</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

  </div>
</div>
@endsection
