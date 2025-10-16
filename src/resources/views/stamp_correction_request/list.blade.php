@php
  /**
   * 受け取り想定:
   * $isAdmin  : bool   管理者なら true（ミドルウェア等で注入）
   * $status   : string 'pending' | 'approved'
   * $requests : array|Collection （各要素）
   *   [
   *     'id'=>1,
   *     'status'=>'pending', // or 'approved'
   *     'user_name'=>'西 伶奈',
   *     'target_at'=>'2023-06-01 09:00:00',
   *     'reason'=>'遅延のため',
   *     'requested_at'=>'2023-06-02 10:00:00',
   *   ]
   */
  $status  = $status ?? 'pending';
  $layout  = ($isAdmin ?? false) ? 'layouts.admin' : 'layouts.app';
  $listRt  = ($isAdmin ?? false) ? 'admin.stamp_correction_request.list'  : 'stamp_correction_request.list';
  $showRt  = ($isAdmin ?? false) ? 'admin.stamp_correction_request.show'  : 'stamp_correction_request.show';
@endphp

@extends($layout)

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
              $tgt = \Carbon\Carbon::parse($row['target_at'] ?? null);
              $req = \Carbon\Carbon::parse($row['requested_at'] ?? null);
            @endphp
            <tr>
              <td class="cell--status">
                {{ $row['status'] === 'approved' ? '承認済み' : '承認待ち' }}
              </td>
              <td class="cell--name">{{ $row['user_name'] ?? '' }}</td>
              <td class="cell--datetime">
                {{ $tgt ? $tgt->isoFormat('YYYY/MM/DD') : '-' }}
              </td>
              <td class="cell--reason">{{ $row['reason'] ?? '' }}</td>
              <td class="cell--datetime">
                {{ $req ? $req->isoFormat('YYYY/MM/DD') : '-' }}
              </td>
              <td class="cell--link">
                <a class="detail-link" href="{{ route($showRt, ['request' => $row['id']]) }}">詳細</a>
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
