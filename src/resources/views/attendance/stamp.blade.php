@extends('layouts.app')

@section('title', '勤怠打刻')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance/stamp.css') }}">
@endsection

@if ($state === 'after')
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
<div class="attend-page">
  <div class="attend-hero">

    {{-- フラッシュメッセージ --}}
    @if (session('status'))
      <div class="flash flash--success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
      <div class="flash flash--error">{{ session('error') }}</div>
    @endif

    {{-- ステータスバッジ --}}
    @php
      /** $state: before|working|break|after */
      $badge = [
        'before'  => ['勤務外',   'muted'],
        'working' => ['出勤中',   'muted'],
        'break'   => ['休憩中',   'muted'],
        'after'   => ['退勤済',   'muted'],
      ][$state] ?? ['勤務外', 'muted'];
    @endphp
    <div class="attend-badge attend-badge--{{ $badge[1] }}">{{ $badge[0] }}</div>

    {{-- 日付 --}}
    <p class="attend-date">
      {{ $today->isoFormat('YYYY年M月D日(ddd)') }}
    </p>

    {{-- 時刻 --}}
    <p class="attend-time" id="attendTime">{{ $displayTime }}</p>

    {{-- 送信用の単一フォーム（外部フォームの影響を受けない） --}}
    <form id="punchForm" method="POST" action="{{ route('attendance.punch') }}">
      @csrf
      <input type="hidden" name="action" id="punchAction">
    </form>
    
    <div class="attend-actions">
      @if ($state === 'before')
        <button type="submit" form="punchForm" class="btn btn--primary" data-action="clock-in">出勤</button>
    
      @elseif ($state === 'working')
        <button type="submit" form="punchForm" class="btn btn--primary" data-action="clock-out">退勤</button>
        <button type="submit" form="punchForm" class="btn btn--ghost"   data-action="break-start">休憩入り</button>
    
      @elseif ($state === 'break')
        <button type="submit" form="punchForm" class="btn btn--ghost"   data-action="break-end">休憩戻り</button>
    
      @else
        <p class="attend-message">お疲れ様でした。</p>
      @endif
    </div>
  </div>
</div>

{{-- 時刻のライブ更新（分解像度、秒は非表示） --}}
<script>
  (()=>{
    const form = document.getElementById('punchForm');
    const inp  = document.getElementById('punchAction');
    document.querySelectorAll('button[form="punchForm"][data-action]').forEach(b=>{
      b.addEventListener('click', ()=>{ inp.value = b.dataset.action; });
    });
  })();
</script>
@endsection
