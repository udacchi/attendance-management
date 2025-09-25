<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>@yield('title','勤怠管理ユーザー')</title>

  <!-- 最小リセット -->
  <link rel="stylesheet" href="https://unpkg.com/ress/dist/ress.min.css" />
  <!-- 共通CSS -->
  <link rel="stylesheet" href="{{ asset('css/layouts/common.css') }}" />

  @yield('css')
</head>

<body class="site-body">
@php
  $isLogin = auth()->check();
@endphp

  <!-- ヘッダー（黒帯・左ロゴ／右メニュー） -->
  <header class="site-header" role="banner">
    <div class="site-header__inner">
      <a class="site-logo" href="{{ $isLogin ? (route('attendance.index') ?? url('/')) : url('/') }}">
        <span class="site-logo__mark">CT</span>
        <span class="site-logo__text">COACHTECH</span>
      </a>

      @if ($isLogin)
        <nav class="global-nav" aria-label="主ナビゲーション">
          <ul class="global-nav__list">
            <li>
              <a class="global-nav__link {{ request()->routeIs('attendance.stamp*') ? 'is-active' : '' }}"
                 href="{{ route('attendance.stamp') }}">勤怠</a>
            </li>
            <li>
              <a class="global-nav__link {{ request()->routeIs('attendance.index*') ? 'is-active' : '' }}"
                 href="{{ route('attendance.index') }}">勤怠一覧</a>
            </li>
            <li>
              <a class="global-nav__link {{ request()->routeIs('requests.*') ? 'is-active' : '' }}"
                 href="{{ route('requests.index') }}">申請</a>
            </li>
            <li>
              <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="global-nav__link global-nav__logout">ログアウト</button>
              </form>
            </li>
          </ul>
        </nav>
      @endif
    </div>
  </header>

  <!-- メイン -->
  <main class="site-main" role="main">
    <div class="container">
      @yield('content')
    </div>
  </main>

</body>

</html>
