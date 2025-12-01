@extends('layouts.app')

@section('title', '勤怠詳細（管理者）')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/detail.css') }}">
@endsection

@section('content')

@php
  $mode       = $mode ?? 'normal';     // 'pending' | 'approved' | 'normal'
  $isEditable = $isEditable ?? false;  // Controller で false 渡している
  // 承認待ち or 承認済み、または編集不可指定のときは読み取り専用
  $isReadOnly = ($mode !== 'normal') || (!$isEditable);
@endphp

<div class="att-detail">
  <div class="att-detail__inner">

    <h1 class="page-title">勤怠詳細</h1>

    <form method="POST"
          action="{{ route('admin.attendance.updateByUserDate', ['user' => $user->id]) }}?date={{ $date->toDateString() }}">
      @csrf
      <input type="hidden" name="date" value="{{ $date->toDateString() }}">

      <div class="detail-card {{ $mode === 'pending' ? 'is-pending' : '' }}">
        <table class="detail-table">
          <tbody>
            {{-- 名前 --}}
            <tr>
              <th>名前</th>
              <td colspan="3" class="cell--center">{{ $record['name'] ?? '' }}</td>
            </tr>

            {{-- 日付 --}}
            <tr>
              <th>日付</th>
              <td class="cell--center cell--ym">{{ $date->isoFormat('YYYY年') }}</td>
              <td class="cell--center cell--md" colspan="2">{{ $date->isoFormat('M月D日') }}</td>
            </tr>

            {{-- 出勤・退勤 --}}
            <tr>
              <th>出勤・退勤</th>
              <td class="cell--inputs">
                <input class="chip-input" type="time" name="clock_in"
                       value="{{ old('clock_in', $record['clock_in'] ?? '') }}" {{ $isReadOnly ? 'readonly' : '' }}>
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <input class="chip-input" type="time" name="clock_out"
                       value="{{ old('clock_out', $record['clock_out'] ?? '') }}" {{ $isReadOnly ? 'readonly' : '' }}>
              </td>
            </tr>
            @error('clock_pair')  <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror

            {{-- 休憩（承認待ち表示では空行を除外して見栄えを整える） --}}
            @php
              $breaks = $record['breaks'] ?? [];
              $isEmpty = fn($b) => in_array(trim($b['start'] ?? ''), ['', '--:--'], true)
                                && in_array(trim($b['end'] ?? ''),   ['', '--:--'], true);

              if ($mode === 'pending') {
                $breaks = array_values(array_filter($breaks, fn($b) => ! $isEmpty($b)));
              } else {
                // normal/approved は末尾に空行が無ければ追加
                if (empty($breaks) || ! $isEmpty(end($breaks) ?: [])) {
                  $breaks[] = ['start'=>'', 'end'=>''];
                }
              }
            @endphp
            @foreach ($breaks as $idx => $b)
              <tr>
                <th>休憩{{ $idx + 1 }}</th>
                <td class="cell--inputs">
                  <input class="chip-input" type="time"
                         name="breaks[{{ $idx }}][start]"
                         value="{{ old("breaks.$idx.start", $b['start'] ?? '') }}" {{ $isReadOnly ? 'readonly' : '' }}>
                </td>
                <td class="cell--tilde">〜</td>
                <td class="cell--inputs">
                  <input class="chip-input" type="time"
                         name="breaks[{{ $idx }}][end]"
                         value="{{ old("breaks.$idx.end", $b['end'] ?? '') }}" {{ $isReadOnly ? 'readonly' : '' }}>
                </td>
              </tr>
            @endforeach
            @error('breaks_range') <tr><td colspan="4" class="cell--error">{{ $message }}</td></tr> @enderror

            {{-- 備考 --}}
            <tr>
              <th>備考</th>
              <td colspan="3" class="cell--inputs">
                <textarea class="note-box" name="note" rows="2" {{ $isReadOnly ? 'readonly' : '' }}>{{ old('note', $record['note'] ?? '') }}</textarea>
              </td>
            </tr>
            @error('note') <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror
          </tbody>
        </table>
      </div>

      {{-- ボタン：編集可能なときのみ表示（= normal かつ isEditable=true のときのみ） --}}
      <div class="detail-actions">
        @unless($isReadOnly)
          <button type="submit" class="btn-primary">修正</button>
        @endunless
      </div>

      {{-- 注意文（状態別） --}}
      @if ($mode === 'pending')
        <p class="pending-note">※承認待ちのため修正はできません。</p>
      @elseif ($mode === 'approved')
        <p class="pending-note">※承認済みのため修正はできません。</p>
      @endif
    </form>

  </div>
</div>
@endsection
