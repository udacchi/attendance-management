@extends('layouts.app')

@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/detail.css') }}">
@endsection

@section('content')
@php
  /** 受け取り想定
   *  $date    : \Carbon\Carbon
   *  $record  : [
   *    'user_id'=>1,'name'=>'西 伶奈',
   *    'clock_in'=>'09:00','clock_out'=>'20:00',
   *    'break1_start'=>'12:00','break1_end'=>'13:00',
   *    'break2_start'=>null,'break2_end'=>null,
   *    'note'=>null,
   *  ]
   */
@endphp

<div class="admin-attendance-detail">
  <div class="admin-attendance-detail__inner">

    <h1 class="page-title">勤怠詳細</h1>

    <div class="detail-card">
      <table class="detail-table">
        <tbody>
          <tr>
            <th>名前</th>
            <td colspan="3" class="cell--center">{{ $record['name'] }}</td>
          </tr>

          <tr>
            <th>日付</th>
            <td class="cell--center cell--ym">{{ $date->isoFormat('YYYY年') }}</td>
            <td class="cell--center cell--md" colspan="2">{{ $date->isoFormat('M月D日') }}</td>
          </tr>

          <tr>
            <th>出勤・退勤</th>
            <td class="cell--inputs">
              <input class="chip-input" type="text" value="{{ $record['clock_in'] ?? '' }}" disabled>
            </td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs">
              <input class="chip-input" type="text" value="{{ $record['clock_out'] ?? '' }}" disabled>
            </td>
          </tr>

          <tr>
            <th>休憩</th>
            <td class="cell--inputs">
              <input class="chip-input" type="text" value="{{ $record['break1_start'] ?? '' }}" disabled>
            </td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs">
              <input class="chip-input" type="text" value="{{ $record['break1_end'] ?? '' }}" disabled>
            </td>
          </tr>

          <tr>
            <th>休憩2</th>
            <td class="cell--inputs">
              <input class="chip-input" type="text" value="{{ $record['break2_start'] ?? '' }}" disabled>
            </td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs">
              <input class="chip-input" type="text" value="{{ $record['break2_end'] ?? '' }}" disabled>
            </td>
          </tr>

          <tr>
            <th>備考</th>
            <td colspan="3" class="cell--inputs">
              <textarea class="note-box" rows="2" disabled>{{ $record['note'] ?? '' }}</textarea>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="detail-actions">
      <a href="{{ route('admin.attendance.edit', ['user' => $record['user_id'], 'date' => $date->toDateString()]) }}" class="btn-primary">修正</a>
    </div>

  </div>
</div>
@endsection
