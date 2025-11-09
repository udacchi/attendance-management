@extends('layouts.app')

@section('title', '勤怠一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/list.css') }}">
@endsection

@section('content')
@php
  /** @var \Carbon\Carbon $date */
  // Controller: $date(Carbon), $records(collection/array)
  $prevDate = $date->copy()->subDay();
  $nextDate = $date->copy()->addDay();
@endphp

<div class="admin-attendance">
  <div class="admin-attendance__inner">

    <h1 class="page-title">
      {{ $date->isoFormat('YYYY年M月D日') }}の勤怠
    </h1>

    {{-- 日付ナビ（前日 / カレンダー / 翌日） --}}
    <div class="date-nav" role="group" aria-label="日付ナビゲーション">
      {{-- 前日 --}}
      <a class="date-nav__btn" href="{{ route('admin.attendance.list', ['date' => $prevDate->toDateString()]) }}">
        <span class="date-nav__arrow">←</span> 前日
      </a>

      {{-- 中央：単日・月ジャンプ --}}
      <form class="date-nav__center" action="{{ route('admin.attendance.list') }}" method="get" id="dateNavForm">
        <i class="date-nav__icon fa-regular fa-calendar" aria-hidden="true"></i>

        {{-- 単日ジャンプ --}}
        <input class="date-nav__input" type="date" name="date"
               value="{{ $date->toDateString() }}"
               aria-label="日付を選択" onchange="this.form.submit()">
      </form>

      {{-- 翌日 --}}
      <a class="date-nav__btn date-nav__btn--right"
         href="{{ route('admin.attendance.list', ['date' => $nextDate->toDateString()]) }}">
        翌日 <span class="date-nav__arrow">→</span>
      </a>
    </div>

    {{-- テーブル --}}
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
                <a href="{{ route('admin.attendance.detail', [
                    'id'   => $row['user_id'],          // ユーザーID
                    'date' => $date->toDateString(),    // その日の date（一覧の日付変数）
                ]) }}" class="detail-link">詳細</a>
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
