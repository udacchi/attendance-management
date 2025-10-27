<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>@yield('title','管理画面')</title>

  <!-- 最小リセット -->
  <link rel="stylesheet" href="https://unpkg.com/ress/dist/ress.min.css" />
  <!-- 共通CSS -->
  <link rel="stylesheet" href="{{ asset('css/layouts/common.css') }}" />
  @yield('css')
</head>

@php
  // 管理者ログイン画面では nav を出さない
  $isLogin = request()->routeIs('admin.login') || request()->is('admin/login');
@endphp

<body>
<header class="admin-header">
  <div class="admin-header__inner">
    <!-- 左：ロゴ -->
    <a href="/" class="header__logo">
      <img src="{{ asset('images/logo.svg') }}" alt="COACHTECH" width="370" height="36">
    </a>

    <!-- 右：nav（ログイン画面では非表示） -->
    @unless($isLogin)
      @if (View::hasSection('link'))
        {{-- 子ビューが nav を差し替える場合 --}}
        @yield('link')
      @else
        {{-- デフォルト nav：勤怠一覧／スタッフ一覧／申請一覧／ログアウト --}}
        <nav class="admin-nav">
          <ul class="admin-nav__list">
            <li><a href="{{ route('admin.attendance.list') }}" class="admin-nav__link">勤怠一覧</a></li>
            <li><a href="{{ route('admin.staff.list') }}"      class="admin-nav__link">スタッフ一覧</a></li>
            <li><a href="{{ route('admin.corrections.list') }}" class="admin-nav__link">申請一覧</a></li>
            <li>
              <form action="{{ route('admin.logout') }}" method="POST" class="adminlogout-form">
                @csrf
                <button type="submit" class="admin-nav__link admin-nav__logout">ログアウト</button>
              </form>
            </li>
          </ul>
        </nav>
      @endif
    @endunless
  </div>
</header>

<div class="content">
  @yield('content')
</div>

</body>
</html>
