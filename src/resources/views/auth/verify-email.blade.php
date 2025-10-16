@extends('layouts.app')

@section('title', 'メール認証のお願い')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/verify-email.css') }}">
@endsection

@section('content')
<div class="verify-email__container">
  <p class="verify-email__message">
    登録していただいたメールアドレスに認証メールを送付しました。<br>
    メール認証を完了して下さい。
  </p>

  <form method="GET" action="{{ route('verification.notice') }}">
    <button type="submit" class="btn btn-primary">
        認証はこちらから
    </button>
  </form>

  <form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <a href="#"
       class="verify-email__resend-link"
       onclick="event.preventDefault(); this.closest('form').submit();">
        認証メールを再送する
    </a>
  </form>
</div>
@endsection