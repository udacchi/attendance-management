@extends('layouts.app')

@section('title', '勤怠詳細')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance/detail.css') }}">
@endsection

@section('content')
@php
  /**
   * 受け取り想定:
   *  $user  : App\Models\User
   *  $date  : \Carbon\Carbon
   *  $record: [
   *    'user_id'=>1,'name'=>'西 伶奈',
   *    'clock_in'=>'09:00','clock_out'=>'20:00',
   *    'break1_start'=>null,'break1_end'=>null,
   *    'break2_start'=>null,'break2_end'=>null,
   *    'note'=>null,
   *  ]
   *  $isLocked: bool  承認待ちなら true
   */
  $locked = $isLocked ?? false;
@endphp

<div class="admin-attendance-detail">
  <div class="admin-attendance-detail__inner">

    <h1 class="page-title">勤怠詳細</h1>

    {{-- メッセージ --}}
    @if (session('status'))
      <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($locked)
      <div class="alert alert-warning">承認待ちのため修正はできません。</div>
    @endif

    {{-- バリデーションエラー（FN039） --}}
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- ★ 編集フォーム（承認待ちのときは disabled で表示のみ） --}}
    <form
      action="{{ route('admin.attendance.update', ['user' => $user->id]) . '?date=' . $date->toDateString() }}"
      method="POST"
    >
      @csrf
      @method('PUT')

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

            {{-- 出勤・退勤（FN038: locked 時は disabled） --}}
            <tr>
              <th>出勤・退勤</th>
              <td class="cell--inputs">
                <input
                  class="chip-input"
                  type="text"
                  name="clock_in"
                  value="{{ old('clock_in', $record['clock_in'] ?? '') }}"
                  placeholder="HH:MM"
                  {{ $locked ? 'disabled' : '' }}
                >
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <input
                  class="chip-input"
                  type="text"
                  name="clock_out"
                  value="{{ old('clock_out', $record['clock_out'] ?? '') }}"
                  placeholder="HH:MM"
                  {{ $locked ? 'disabled' : '' }}
                >
              </td>
            </tr>

            {{-- 休憩1 --}}
            <tr>
              <th>休憩</th>
              <td class="cell--inputs">
                <input
                  class="chip-input"
                  type="text"
                  name="break1_start"
                  value="{{ old('break1_start', $record['break1_start'] ?? '') }}"
                  placeholder="HH:MM"
                  {{ $locked ? 'disabled' : '' }}
                >
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <input
                  class="chip-input"
                  type="text"
                  name="break1_end"
                  value="{{ old('break1_end', $record['break1_end'] ?? '') }}"
                  placeholder="HH:MM"
                  {{ $locked ? 'disabled' : '' }}
                >
              </td>
            </tr>

            {{-- 休憩2 --}}
            <tr>
              <th>休憩2</th>
              <td class="cell--inputs">
                <input
                  class="chip-input"
                  type="text"
                  name="break2_start"
                  value="{{ old('break2_start', $record['break2_start'] ?? '') }}"
                  placeholder="HH:MM"
                  {{ $locked ? 'disabled' : '' }}
                >
              </td>
              <td class="cell--tilde">〜</td>
              <td class="cell--inputs">
                <input
                  class="chip-input"
                  type="text"
                  name="break2_end"
                  value="{{ old('break2_end', $record['break2_end'] ?? '') }}"
                  placeholder="HH:MM"
                  {{ $locked ? 'disabled' : '' }}
                >
              </td>
            </tr>

            {{-- 備考（FN039: 必須） --}}
            <tr>
              <th>備考</th>
              <td colspan="3" class="cell--inputs">
                <textarea
                  class="note-box"
                  name="note"
                  rows="2"
                  placeholder="修正理由や注意点などを記入してください"
                  {{ $locked ? 'disabled' : '' }}
                >{{ old('note', $record['note'] ?? '') }}</textarea>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      {{-- アクション（FN038: locked なら押せない） --}}
      <div class="detail-actions">
        <button type="submit" class="btn-primary" {{ $locked ? 'disabled' : '' }}>修正</button>

        {{-- もし従来の編集専用画面を残すなら、?date を付けてリンクも置けます（任意） --}}
        {{-- <a href="{{ route('admin.attendance.edit', ['user' => $user->id]) . '?date=' . $date->toDateString() }}" class="btn-secondary">編集画面へ</a> --}}
      </div>
    </form>

  </div>
</div>
@endsection
