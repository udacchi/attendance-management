@extends('layouts.app')

@section('title', '会員登録')

@section('css')
<link rel="stylesheet" href="{{ asset('css/auth/register.css') }}">
@endsection

@section('content')
<div class="user-register">
  <div class="user-register__panel">
    <h1 class="user-register__title">会員登録</h1>

    <form class="user-register__form" action="{{ route('register') }}" method="POST" novalidate>
      @csrf

      <div class="form-group">
        <label for="name" class="form-label">名前</label>
        <input id="name" name="name" type="text" class="form-input" value="{{ old('name') }}" autocomplete="name" autofocus required>
        @error('name') <p class="form-error">{{ $message }}</p> @enderror
      </div>

      <div class="form-group">
        <label for="email" class="form-label">メールアドレス</label>
        <input id="email" name="email" type="email" class="form-input" value="{{ old('email') }}" autocomplete="email" required>
        @error('email') <p class="form-error">{{ $message }}</p> @enderror
      </div>

      <div class="form-group">
        <label for="password" class="form-label">パスワード</label>
        <input id="password" name="password" type="password" class="form-input" autocomplete="new-password" required>
        @error('password') <p class="form-error">{{ $message }}</p> @enderror
      </div>

      <div class="form-group">
        <label for="password_confirmation" class="form-label">パスワード確認</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="form-input" autocomplete="new-password" required>
      </div>

      <button type="submit" class="register-button">登録する</button>

      <p class="register-to-login">
        <a href="{{ route('login') }}">ログインはこちら</a>
      </p>
    </form>
  </div>
</div>
@endsection
