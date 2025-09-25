@extends('layouts.app')

@section('title', 'メール認証のお願い')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/verify-email.css') }}">
@endsection

@section('content')
<div class="verify-page">
  <div class="verify-card">
    {{-- 送信完了フラッシュ --}}
    @if (session('status') == 'verification-link-sent')
      <p class="flash">
        認証用リンクを再送しました。メールをご確認ください。
      </p>
    @endif

    <div class="verify-message">
      <p>登録していただいたメールアドレスに認証メールを送付しました。<br>
         メール認証を完了してください。</p>
    </div>

    {{-- 中央のグレーボタン（スクショ準拠） --}}
    <a class="verify-btn" href="javascript:void(0);" aria-disabled="true">認証はこちらから</a>
    {{-- 実際の認証はメール内リンクで行います。ボタンは視覚的要素です --}}

    {{-- 再送リンク（POST / verification.send） --}}
    <form method="POST" action="{{ route('verification.send') }}" class="resend-form">
      @csrf
      <button type="submit" class="resend-link">認証メールを再送する</button>
    </form>
  </div>
</div>
@endsection
