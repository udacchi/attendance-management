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

<div class="admin-att">
  <div class="admin-att__inner">

    <h1 class="page-title">
      {{ $date->isoFormat('YYYY年M月D日') }}の勤怠
    </h1>

    {{-- 日付ナビ（前日 / カレンダー / 翌日） --}}
    <div class="date-nav">
      {{-- 前日 --}}
      <a class="date-nav__btn" 
         href="{{ route('admin.attendance.list', ['date' => $prevDate->toDateString()]) }}">
        <span class="date-nav__arrow">&#8592;</span> 前日
      </a>

      {{-- 中央：単日・月ジャンプ --}}
      <form id="dateNavForm" class="date-nav__center" action="{{ route('admin.attendance.list') }}" method="get">

        <!-- 右画像風：カレンダーのマス目＋チェック -->
        <svg class="date-nav__icon" viewBox="0 0 24 24" aria-hidden="true">
          <!-- 外枠 -->
          <rect x="3" y="4.5" width="18" height="16" rx="2.5"
                fill="none" stroke="currentColor" stroke-width="1.8"/>
          <!-- 綴じ具 -->
          <line x1="8" y1="4.5" x2="8" y2="2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <line x1="16" y1="4.5" x2="16" y2="2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <!-- ヘッダー仕切り -->
          <line x1="3" y1="8.5" x2="21" y2="8.5" stroke="currentColor" stroke-width="1.6"/>

          <!-- マス目（3列×2段） -->
          <rect x="5.4" y="10.2" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.1"/>
          <rect x="10.0" y="10.2" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.1"/>
          <rect x="14.6" y="10.2" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.1"/>
          <rect x="5.4" y="14.4" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.1"/>
          <rect x="10.0" y="14.4" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.1"/>
          <rect x="14.6" y="14.4" width="4" height="3.4" rx="0.6" fill="none" stroke="currentColor" stroke-width="1.1"/>

          <!-- チェック（2つのマスにチェック） -->
          <path d="M10.6 12 l0.8 0.9 1.7-1.7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M6.0 16.2 l1.0 1.0 2.0-2.0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        
      
        {{-- ★ 表示用は format or isoFormat --}}
        <span id="dateDisplay" class="date-nav__display">{{ $date->format('Y/m/d') }}</span>
      
        {{-- ★ 透明で中央一帯を覆う input（ここがクリックを拾う） --}}
        <input id="datePicker" class="date-nav__input" type="date" name="date"
               value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()">
      </form>
      
      <script>
        (() => {
          const form    = document.getElementById('dateNavForm');
          const input   = document.getElementById('datePicker');
          const display = document.getElementById('dateDisplay');
        
          // 中央ブロックをクリックしたらピッカーを開く
          form.addEventListener('click', (e) => {
            if (typeof input.showPicker === 'function') input.showPicker();
            else input.focus();
          });
        
          // 値が変わったら表示も更新（YYYY/MM/DD）
          const updateText = () => {
            if (!input.value) return;
            const [y, m, d] = input.value.split('-');
            display.textContent = `${y}/${m}/${d}`;
          };
          input.addEventListener('change', updateText);
          input.addEventListener('input',  updateText);
        })();
        </script>
        

      {{-- 翌日 --}}
      <a class="date-nav__btn date-nav__btn--right"
         href="{{ route('admin.attendance.list', ['date' => $nextDate->toDateString()]) }}">
        翌日 <span class="date-nav__arrow">&#8594;</span>
      </a>
    </div>

    {{-- テーブル --}}
    <div class="att-table__wrap">
      <table class="att-table">
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
          @php
            // 値が「- / ー / – / — / ―」だけ（前後スペース含む）なら空文字にする
            $clean = function ($v) {
              if ($v === null) return '';
              $s = trim((string)$v);
              return preg_match('/^[-ー–—―]+$/u', $s) ? '' : $s;
            };
          @endphp
        
          @foreach ($records as $row)
            <tr>
              <td class="cell--name">{{ $row['name'] }}</td>
              <td class="cell--time">{{ $clean($row['clock_in']  ?? null) }}</td>
              <td class="cell--time">{{ $clean($row['clock_out'] ?? null) }}</td>
              <td class="cell--time">{{ $clean($row['break_total'] ?? null) }}</td>
              <td class="cell--time">{{ $clean($row['work_total'] ?? null) }}</td>
              <td class="cell--link">
                <a href="{{ route('admin.attendance.detail', [
                  'id' => $row['user_id'],
                  'date' => $date->toDateString(),
                ]) }}" class="detail-link">詳細</a>
              </td>
            </tr>
          @endforeach
        </tbody>
                
      </table>
    </div>

  </div>
</div>
@endsection
