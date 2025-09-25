@extends('layouts.admin')

@section('title', '管理者ログイン')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/login.css') }}">
@endsection

@section('content')
<div class="admin-login">
  <div class="admin-login__panel">
    <h1 class="admin-login__title">管理者ログイン</h1>

    <form class="admin-login__form" action="{{ route('admin.login') }}" method="POST" novalidate>
      @csrf

      <div class="form-group">
        <label for="email" class="form-label">メールアドレス</label>
        <input id="email" name="email" type="email" class="form-input" value="{{ old('email') }}" autocomplete="email" autofocus required>
        @error('email')
          <p class="form-error">{{ $message }}</p>
        @enderror
      </div>

      <div class="form-group">
        <label for="password" class="form-label">パスワード</label>
        <input id="password" name="password" type="password" class="form-input" autocomplete="current-password" required>
        @error('password')
          <p class="form-error">{{ $message }}</p>
        @enderror
      </div>

      <button type="submit" class="admin-login__button">管理者ログインする</button>
    </form>
  </div>
</div>
@endsection
