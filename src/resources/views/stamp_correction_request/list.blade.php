@php
  // 既定値の補強：管理者判定は guard から自動で拾う
  $isAdmin = ($isAdmin ?? null) ?? auth('admin')->check();
  $status  = $status ?? 'pending';
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

    {{-- タブ --}}
    <div class="tabs">
      <a href="{{ route($listRt, ['status'=>'pending']) }}"
         class="tab {{ $status==='pending' ? 'is-active' : '' }}">承認待ち</a>
      <a href="{{ route($listRt, ['status'=>'approved']) }}"
         class="tab {{ $status==='approved' ? 'is-active' : '' }}">承認済み</a>
    </div>

    {{-- テーブル --}}
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
              // どのカラム名でも拾えるようにフォールバック
              $targetRaw  = $row->target_at ?? $row->work_date ?? $row->target_date ?? null;
              $requestRaw = $row->requested_at ?? $row->created_at ?? null;
        
              $tgt        = $targetRaw  ? \Carbon\Carbon::parse($targetRaw)  : null;
              $req        = $requestRaw ? \Carbon\Carbon::parse($requestRaw) : null;
              $targetDate = $tgt?->toDateString();
        
              $isAdmin = ($isAdmin ?? null) ?? auth('admin')->check();
              $isApproved = ($row->status ?? '') === 'approved';
            @endphp
            <tr>
              <td class="cell--status">{{ $isApproved ? '承認済み' : '承認待ち' }}</td>
              <td class="cell--name">{{ $row->user_name ?? '' }}</td>
              <td class="cell--datetime">{{ $tgt ? $tgt->isoFormat('YYYY/MM/DD') : '' }}</td>
              <td class="cell--reason">{{ $row->reason ?? '' }}</td>
              <td class="cell--datetime">{{ $req ? $req->isoFormat('YYYY/MM/DD') : '' }}</td>
        
              {{-- ▼ ここから分岐実装 --}}
              <td class="cell--link">
                @if (!$targetDate)
                  {{-- 対象日が取れない場合は無効表示（安全側） --}}
                  <span class="detail-link is-disabled" aria-disabled="true">詳細</span>
                @else
                  @php
                    $uid = $row->user_id ?? $row->requested_user_id ?? null;
                  @endphp
              
                  @if ($isAdmin)
                    {{-- 管理者：承認待ちも承認済みも、全て approve 詳細ページへ --}}
                    <a class="detail-link"
                       href="{{ route('stamp_correction_request.approve', [
                           'attendance_correct_request_id' => $row->id
                       ]) }}">
                      詳細
                    </a>
                  @else
                    {{-- 一般ユーザー側の分岐はそのまま --}}
                    @if ($isApproved)
                      <a class="detail-link"
                         href="{{ route('stamp_correction_request.approve', [
                             'attendance_correct_request_id' => $row->id,
                             'view' => 'approved'
                         ]) }}">
                        詳細
                      </a>
                    @else
                      <a class="detail-link"
                         href="{{ route('attendance.detail', ['date' => $targetDate]) }}">
                        詳細
                      </a>
                    @endif
                  @endif
                @endif
              </td>
              
              {{-- ▲ ここまで分岐実装 --}}
            </tr>
          @empty
            <tr>
              <td colspan="6" class="cell--empty">表示できる申請はありません</td>
            </tr>
          @endforelse
        </tbody>
    </div>

  </div>
</div>
@endsection
