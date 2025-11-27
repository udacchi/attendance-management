@extends('layouts.app')

@section('title', '修正申請承認')

@section('css')
<link rel="stylesheet" href="{{ asset('css/stamp_correction_request/approve.css') }}">
@endsection

@section('content')
@php
  $tz = config('app.timezone', 'Asia/Tokyo');
  $isPending = $isPending ?? (($req->status ?? null) === 'pending');
@endphp

<div class="approve-page">
  <div class="approve-page__inner">

    <h1 class="page-title">勤怠詳細</h1>

    <div class="approve-card">
      <table class="approve-table">
        <tbody>
          <tr>
            <th>名前</th>
            <td colspan="3" class="cell--center">{{ $record['name'] ?? '' }}</td>
          </tr>
          <tr>
            <th>日付</th>
            <td class="cell--center cell--ym">{{ optional($date)->isoFormat('YYYY年') }}</td>
            <td class="cell--center cell--md" colspan="2">{{ optional($date)->isoFormat('M月D日') }}</td>
          </tr>
          <tr>
            <th>出勤・退勤</th>
            <td class="cell--inputs">
              <input class="chip-input" type="time" name="clock_in"
                     value="{{ $record['clock_in'] ?? '' }}" readonly>
            </td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs">
              <input class="chip-input" type="time" name="clock_out"
                     value="{{ $record['clock_out'] ?? '' }}" readonly>
            </td>
          </tr>

          @foreach (($record['breaks'] ?? []) as $i => $b)
            <tr>
              <th class="cell">休憩{{ $i + 1 }}</th>
              <td class="cell--inputs">
                <input class="chip-input" type="time" value="{{ $b['start'] ?? '' }}" readonly>
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <input class="chip-input" type="time" value="{{ $b['end'] ?? '' }}" readonly>
              </td>
            </tr>
          @endforeach

          <tr>
            <th>備考</th>
            <td colspan="3" class="cell--inputs">
              <textarea class="note-box" rows="2" readonly>{{ $record['note'] ?? '' }}</textarea>
            </td>
          </tr>

          @if (!empty($req->proposed_note))
            <tr>
              <th>申請メモ</th>
              <td colspan="3" class="cell--inputs">
                <div class="approve-note">{{ $req->proposed_note }}</div>
              </td>
            </tr>
          @endif
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
