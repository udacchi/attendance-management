@extends('layouts.app')

@section('title', 'ログイン')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
@endsection

@section('content')
<div class="user-login">
  <div class="user-login__panel">
    <h1 class="user-login__title">ログイン</h1>

    <form class="user-login__form" action="{{ route('login') }}" method="POST" novalidate>
      @csrf

      <div class="form-group">
        <label for="email" class="form-label">メールアドレス</label>
        <input id="email" name="email" type="email" class="form-input" value="{{ old('email') }}" autocomplete="email" autofocus required>
        @error('email') <p class="form-error">{{ $message }}</p> @enderror
      </div>

      <div class="form-group">
        <label for="password" class="form-label">パスワード</label>
        <input id="password" name="password" type="password" class="form-input" autocomplete="current-password" required>
        @error('password') <p class="form-error">{{ $message }}</p> @enderror
      </div>

      <button type="submit" class="login-button">ログインする</button>

      <p class="login-to-register">
        <a href="{{ route('register') }}">会員登録はこちら</a>
      </p>
    </form>
  </div>
</div>
@endsection
