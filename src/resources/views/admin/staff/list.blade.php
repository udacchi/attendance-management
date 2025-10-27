@extends('layouts.app')

@section('title', 'スタッフ一覧')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/staff/list.css') }}">
@endsection

@section('content')
@php
  /**
   * 受け取り想定
   * $staffs: Collection|array
   *          各要素は App\Models\User もしくは
   *          ['id'=>1,'name'=>'山田 太郎','email'=>'taro@example.com'] の形
   * $month : \Carbon\Carbon  月次リンクに使う対象月（未提供なら今月）
   */
  $month = isset($month) ? $month : \Carbon\Carbon::now()->startOfMonth();
@endphp

<div class="admin-staff-list">
  <div class="admin-staff-list__inner">

    <h1 class="page-title">スタッフ一覧</h1>

    <div class="staff-table__wrap">
      <table class="staff-table">
        <thead>
          <tr>
            <th>名前</th>
            <th>メールアドレス</th>
            <th>月次勤怠</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($staffs as $s)
            @php
              // モデルでも配列でも参照できるよう吸収
              $id    = is_object($s) ? $s->id    : ($s['id'] ?? null);
              $name  = is_object($s) ? $s->name  : ($s['name'] ?? '');
              $email = is_object($s) ? $s->email : ($s['email'] ?? '');
            @endphp
            <tr>
              <td class="cell--name">
                <a
                  href="{{ route('admin.attendance.staff', ['id' => $id, 'month' => $month->format('Y-m')]) }}"
                  class="name-link" aria-label="{{ $name }}さんの勤怠一覧へ">
                  {{ $name }}
                </a>
              </td>
              <td class="cell--email">{{ $email }}</td>
              <td class="cell--link">
                <a class="detail-link"
                   href="{{ route('admin.attendance.staff', ['id' => $id, 'month' => $month->format('Y-m')]) }}">
                  詳細
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="3" class="cell--empty">スタッフが登録されていません</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

  </div>
</div>
@endsection
