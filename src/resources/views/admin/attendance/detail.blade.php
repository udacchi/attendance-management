@extends('layouts.app')

@section('title', '勤怠詳細（管理者）')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/detail.css') }}">
@endsection

@section('content')
@php
  /**
   * 受け取り想定:
   * $user   : App\Models\User
   * $date   : Carbon\Carbon  対象日
   * $record : [
   *   'user_id','name','clock_in','clock_out','note','breaks'=>[ ['start','end'], ... , ['',''] ]
   * ]
   */
@endphp

<div class="att-detail">
  <div class="att-detail__inner">

    <h1 class="page-title">勤怠詳細</h1>

    {{-- バリデーション等 --}}
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul>
          @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif
    @if (session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- ★ フォーム（POSTで /admin/attendance/{user}/update に送る。date は hidden でPOST） --}}
    <form method="POST"
          action="{{ route('admin.attendance.updateByUserDate', ['user' => $user->id]) }}?date={{ $date->toDateString() }}">
      @csrf
    
      <div class="detail-card">
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
                       value="{{ old('clock_in', $record['clock_in'] ?? '') }}">
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <input class="chip-input" type="time" name="clock_out"
                       value="{{ old('clock_out', $record['clock_out'] ?? '') }}">
              </td>
            </tr>
            @error('clock_in')  <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror
            @error('clock_out') <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror
    
            {{-- 休憩 --}}
            @foreach (($record['breaks'] ?? []) as $idx => $b)
              <tr>
                <th>休憩{{ $idx + 1 }}</th>
                <td class="cell--inputs">
                  <input class="chip-input" type="time"
                         name="breaks[{{ $idx }}][start]"
                         value="{{ old("breaks.$idx.start", $b['start'] ?? '') }}">
                </td>
                <td class="cell--tilde">〜</td>
                <td class="cell--inputs">
                  <input class="chip-input" type="time"
                         name="breaks[{{ $idx }}][end]"
                         value="{{ old("breaks.$idx.end", $b['end'] ?? '') }}">
                </td>
              </tr>
              @error("breaks.$idx.start") <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror
              @error("breaks.$idx.end")   <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror
            @endforeach
    
            {{-- 備考 --}}
            <tr>
              <th>備考</th>
              <td colspan="3" class="cell--inputs">
                <textarea class="note-box" name="note" rows="2">{{ old('note', $record['note'] ?? '') }}</textarea>
              </td>
            </tr>
            @error('note') <tr><td colspan="4" class="error">{{ $message }}</td></tr> @enderror
          </tbody>
        </table>
      </div>
    
      <div class="detail-actions">
        <button type="submit" class="btn-primary">修正</button>
      </div>
    </form>


  </div>
</div>
@endsection
