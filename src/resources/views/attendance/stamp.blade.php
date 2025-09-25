@extends('layouts.app')

@section('title', '勤怠打刻')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance/stamp.css') }}">
@endsection

@section('content')
<div class="attend-page">
  <div class="attend-hero">
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

    {{-- アクション（状態で出し分け） --}}
    <div class="attend-actions">
      @if ($state === 'before')
        <form class="attend-form" action="{{ route('attendance.stamp') }}" method="POST">
          @csrf
          <input type="hidden" name="action" value="clock_in">
          <button type="submit" class="btn btn--primary">出勤</button>
        </form>

      @elseif ($state === 'working')
        <form class="attend-form" action="{{ route('attendance.stamp') }}" method="POST">
          @csrf
          <input type="hidden" name="action" value="clock_out">
          <button type="submit" class="btn btn--primary">退勤</button>
        </form>

        <form class="attend-form" action="{{ route('attendance.stamp') }}" method="POST">
          @csrf
          <input type="hidden" name="action" value="break_in">
          <button type="submit" class="btn btn--ghost">休憩入</button>
        </form>

      @elseif ($state === 'break')
        <form class="attend-form" action="{{ route('attendance.stamp') }}" method="POST">
          @csrf
          <input type="hidden" name="action" value="break_out">
          <button type="submit" class="btn btn--ghost">休憩戻</button>
        </form>

      @elseif ($state === 'after')
        <p class="attend-message">お疲れ様でした。</p>
      @endif
    </div>
  </div>
</div>

{{-- 時刻のライブ更新（分解像度、秒は非表示） --}}
<script>
  (function(){
    const el = document.getElementById('attendTime');
    if(!el) return;
    function pad(n){ return (n<10?'0':'')+n; }
    function tick(){
      const now = new Date();
      el.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes());
    }
    tick();
    setInterval(tick, 10*1000); // 10秒ごとに分を追従
    // 二重送信防止
    document.querySelectorAll('.attend-form').forEach(f=>{
      f.addEventListener('submit', e=>{
        const btn = f.querySelector('button[type="submit"]');
        if(btn){ btn.disabled = true; btn.classList.add('is-loading'); }
      });
    });
  })();
</script>
@endsection
