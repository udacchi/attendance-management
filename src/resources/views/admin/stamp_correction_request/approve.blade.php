@extends('layouts.admin')

@section('title', '修正申請承認')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/stamp_correction_request/approve.css') }}">
@endsection

@section('content')
@php
  /**
   * 受け取り想定:
   * $date : \Carbon\Carbon (対象日)
   * $record : [
   *   'user_id'=>1,'name'=>'西 伶奈',
   *   'clock_in'=>'09:00','clock_out'=>'18:00',
   *   'break1_start'=>'12:00','break1_end'=>'13:00',
   *   'break2_start'=>null,'break2_end'=>null,
   *   'note'=>'電車遅延のため'
   * ]
   * $requestId : int  … この修正申請のID
   * $status    : 'pending' | 'approved'
   */
  $approved = (session('approved') === true) || ($status ?? 'pending') === 'approved';
@endphp

<div class="approve-page">
  <div class="approve-page__inner">

    <h1 class="page-title">勤怠詳細</h1>

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

          <tr>
            <th>出勤・退勤</th>
            <td class="cell--inputs"><span class="chip">{{ $record['clock_in'] ?? '-' }}</span></td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs"><span class="chip">{{ $record['clock_out'] ?? '-' }}</span></td>
          </tr>

          <tr>
            <th>休憩</th>
            <td class="cell--inputs"><span class="chip">{{ $record['break1_start'] ?? '-' }}</span></td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs"><span class="chip">{{ $record['break1_end'] ?? '-' }}</span></td>
          </tr>

          <tr>
            <th>休憩2</th>
            <td class="cell--inputs"><span class="chip">{{ $record['break2_start'] ?? '' }}</span></td>
            <td class="cell--tilde">〜</td>
            <td class="cell--inputs"><span class="chip">{{ $record['break2_end'] ?? '' }}</span></td>
          </tr>

          <tr>
            <th>備考</th>
            <td colspan="3" class="cell--inputs">
              <div class="note-box">{{ $record['note'] ?? '' }}</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 承認アクション -->
    <div class="approve-actions" id="approveArea">
      @if($approved)
        <span class="badge-approved" id="approvedBadge">承認済み</span>
      @else
        <form id="approveForm"
              action="{{ route('admin.stamp_correction_request.approve', ['request' => $requestId]) }}"
              method="POST"
              data-approve-url="{{ route('admin.stamp_correction_request.approve', ['request' => $requestId]) }}">
          @csrf
          <button type="submit" class="btn-primary" id="approveBtn">承認</button>
        </form>
      @endif
    </div>

  </div>
</div>

<!-- Progressive Enhancement: 送信後にその場で「承認済み」に変える -->
<script>
  (function(){
    const form = document.getElementById('approveForm');
    if(!form) return;

    const btn  = document.getElementById('approveBtn');
    const url  = form.dataset.approveUrl;
    const token = form.querySelector('input[name="_token"]').value;

    form.addEventListener('submit', async (e) => {
      // AJAXで送れる場合はページ遷移なしで更新
      e.preventDefault();
      btn.disabled = true;
      btn.textContent = '処理中…';

      try{
        const res = await fetch(url, {
          method: 'POST',
          headers: {'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest'},
        });

        if(!res.ok){ throw new Error('approve failed'); }

        // 成功: フォームを「承認済み」に差し替え
        const area = document.getElementById('approveArea');
        area.innerHTML = '<span class="badge-approved" id="approvedBadge">承認済み</span>';
      }catch(err){
        // 失敗時は通常送信にフォールバック
        form.submit();
      }
    }, { once:false });
  })();
</script>
@endsection
