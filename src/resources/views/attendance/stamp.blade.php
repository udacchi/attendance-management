@extends('layouts.app')

@section('title', '勤怠打刻')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance/stamp.css') }}">
@endsection

@php
  $normState = match ($state) {
    'checkedout', 'checked_out' => 'after',
    'breaking'                  => 'break',
    default                     => $state, // before / working / break / after
  };
@endphp

@if ($normState === 'after')
  @section('user_nav')
    <nav class="global-nav" aria-label="主ナビゲーション">
      <ul class="global-nav__list">
        {{-- 今月の出勤一覧（当月の範囲を付けて一覧へ） --}}
        <li>
          <a class="global-nav__link"
             href="{{ route('attendance.list', [
                    'from' => \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                    'to'   => \Carbon\Carbon::now()->endOfMonth()->toDateString(),
                  ]) }}">
            今月の出勤一覧
          </a>
        </li>

        {{-- 申請一覧：ルート名はプロジェクトに合わせて変更してください --}}
        <li>
          <a class="global-nav__link"
             href="{{ route('stamp_correction_request.list') }}">
            申請一覧
          </a>
        </li>

        {{-- ログアウト --}}
        <li>
          <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="global-nav__link global-nav__logout">ログアウト</button>
          </form>
        </li>
      </ul>
    </nav>
  @endsection
@endif

@section('content')
<div class="att-page">
  <div class="att-hero">

    {{-- フラッシュメッセージ --}}
    @if (session('status'))
      <div class="flash flash--success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
      <div class="flash flash--error">{{ session('error') }}</div>
    @endif

    {{-- ステータスバッジ --}}
    @php
      $badge = [
        'before'  => ['勤務外',   'muted'],
        'working' => ['出勤中',   'muted'],
        'break'   => ['休憩中',   'muted'],
        'after'   => ['退勤済',   'muted'],
      ][$normState] ?? ['勤務外', 'muted'];
    @endphp
    
    <div class="att-badge att-badge--{{ $badge[1] }}">{{ $badge[0] }}</div>

    {{-- 日付 --}}
    <p class="att-date">
      {{ $today->isoFormat('YYYY年M月D日(ddd)') }}
    </p>

    {{-- 時刻 --}}
    <p class="att-time" id="attendTime">{{ $displayTime }}</p>

    

    <form id="punchForm" method="POST" action="{{ route('attendance.punch') }}">
      @csrf
    </form>
    
    <div class="att-actions">
      @if ($state === 'before')
        <button type="submit" form="punchForm" name="action" value="clock-in"  class="btn btn--primary">出勤</button>
    
      @elseif ($state === 'working')
        <button type="submit" form="punchForm" name="action" value="clock-out"   class="btn btn--primary">退勤</button>
        <button type="submit" form="punchForm" name="action" value="break-start" class="btn btn--ghost">休憩入り</button>
    
      @elseif ($state === 'break')
        <button type="submit" form="punchForm" name="action" value="break-end"   class="btn btn--ghost">休憩戻り</button>
    
      @else
        <p class="att-message">お疲れ様でした。</p>
      @endif
    </div>
    
  </div>
</div>

{{-- 時刻のライブ更新（分解像度、秒は非表示） --}}
<script>
  (() => {
    const el = document.getElementById('attendTime');
    if (!el) return;
    const pad = n => ('0' + n).slice(-2);
    const tick = () => {
      const d = new Date();
      el.textContent = `${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };
    // 初回即時＆30秒ごと更新（分解像度、秒は表示しない）
    tick();
    setInterval(tick, 60 * 1000);
  })();
  </script>

@endsection
