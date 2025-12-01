@extends('layouts.app')

@section('title', 'メール認証のお願い')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/verify-email.css') }}">
@endsection

@section('content')
<div class="verify-email__container">
  <p class="verify-email__message">
    登録していただいたメールアドレスに認証メールを送付しました。<br>
    メール認証を完了してください。
  </p>

  {{-- ▼ MailHog を新しいタブで開く「ボタン」：常に表示 --}}
  @php $previewUrl = config('services.mail_preview_url', 'http://localhost:8025'); @endphp
  @if (Route::has('open.mailhog'))
    <form method="GET" action="{{ route('open.mailhog') }}" target="_blank" class="verify-email__actions">
      <button type="submit" class="btn">認証はこちらから</button>
    </form>
  @else
    {{-- ルート未作成でも動くフォールバック（直接URL） --}}
    <form method="GET" action="{{ $previewUrl }}" target="_blank" class="verify-email__actions">
      <button type="submit" class="btn">認証はこちらから</button>
    </form>
  @endif

  {{-- ▼ 再送はリンク見た目 + 内部はPOST（Fortifyの verification.send） --}}
  <form id="resendForm"
        method="POST"
        action="{{ route('verification.send') }}"
        class="verify-email__actions"
        style="margin-top:20px;">
    @csrf
    <a href="{{ route('verification.send') }}"
       class="verify-email__resend-link"
       onclick="event.preventDefault(); document.getElementById('resendForm').submit();">
      認証メールを再送する
    </a>
    <noscript>
      <button type="submit" class="link-like">（JS無効時はこちら）</button>
    </noscript>
  </form>
</div>
@endsection
