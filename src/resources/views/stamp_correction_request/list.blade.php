@php
  $status  = $status ?? 'pending';
  $layout  = ($isAdmin ?? false) ? 'layouts.admin' : 'layouts.app';

  // 一覧は共通
  $listRt  = 'stamp_correction_request.list';
@endphp

@extends('layouts.app')

@section('title', '申請一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/stamp_correction_request/list.css') }}">
@endsection

@section('content')
<div class="scr-list">
  <div class="scr-list__inner">

    <h1 class="page-title">申請一覧</h1>

    <!-- タブ（承認待ち / 承認済み） -->
    <div class="tabs">
      <a href="{{ route($listRt, ['status'=>'pending']) }}"
         class="tab {{ $status==='pending' ? 'is-active' : '' }}">承認待ち</a>
      <a href="{{ route($listRt, ['status'=>'approved']) }}"
         class="tab {{ $status==='approved' ? 'is-active' : '' }}">承認済み</a>
    </div>

    <!-- テーブル -->
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>状態</th>
            <th>名前</th>
            <th>対象日時</th>
            <th>申請理由</th>
            <th>申請日時</th>
            <th>詳細</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($requests as $row)
            @php
              $tgt = $row->target_at ? \Carbon\Carbon::parse($row->target_at) : null;
              $req = $row->requested_at ? \Carbon\Carbon::parse($row->requested_at) : null;
            @endphp
            <tr>
              <td class="cell--status">
                {{ $row->status === 'approved' ? '承認済み' : '承認待ち' }}
              </td>
              <td class="cell--name">{{ $row->user_name ?? '' }}</td>
              <td class="cell--datetime">
                {{ $tgt ? $tgt->isoFormat('YYYY/MM/DD') : '-' }}
              </td>
              <td class="cell--reason">{{ $row->reason ?? '' }}</td>
              <td class="cell--datetime">
                {{ $req ? $req->isoFormat('YYYY/MM/DD') : '-' }}
              </td>
              <td class="cell--link">
                @php
                  // 対象日（YYYY-MM-DD）を安全に取り出す
                  $targetDate = $tgt ? $tgt->toDateString() : null;
                @endphp
              
                @if(($isAdmin ?? false))
                  {{-- 管理者：承認画面へ --}}
                  <a class="detail-link"
                     href="{{ route('stamp_correction_request.approve', ['attendance_correct_request_id' => $row->id]) }}">
                    詳細
                  </a>
                @else
                  {{-- 一般ユーザー：勤怠詳細へ（自分の詳細なので日付のみでOK想定） --}}
                  @if ($targetDate && Route::has('attendance.detail'))
                    <a class="detail-link"
                       href="{{ route('attendance.detail', ['date' => $targetDate]) }}">
                      詳細
                    </a>
                  @else
                    詳細
                  @endif
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="cell--empty">表示できる申請はありません</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

  </div>
</div>
@endsection
