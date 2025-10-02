<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>@yield('title', request()->is('admin*') ? '管理画面' : '勤怠管理ユーザー')</title>

  <!-- 最小リセット -->
  <link rel="stylesheet" href="https://unpkg.com/ress/dist/ress.min.css" />
  <!-- 共通CSS（ユーザー／管理者どちらでも共通で使う） -->
  <link rel="stylesheet" href="{{ asset('css/layouts/common.css') }}" />

  {{-- 画面ごとの追加CSS --}}
  @yield('css')
</head>

@php
  // 現在地（/admin配下かどうか）
  $isAdminArea   = request()->is('admin*');

  // 認証状態（通常ユーザー／管理者ガード）
  $userLoggedIn  = auth()->check();
  // adminガードを使っていない場合もあるので、存在チェックを挟んで安全に
  try {
      $adminLoggedIn = auth('admin')->check();
  } catch (\Throwable $e) {
      $adminLoggedIn = false;
  }

  // ナビを出すかどうか（管理者ログイン画面は非表示、ユーザーは未ログインなら非表示）
  $hideNav = $isAdminArea
      ? (request()->routeIs('admin.login') || request()->is('admin/login'))
      : !$userLoggedIn;
@endphp

<body>
<header class="{{ $isAdminArea ? 'admin-header' : 'user-header' }}">
  <div class="{{ $isAdminArea ? 'admin-header__inner' : 'user-header__inner' }}">
    <!-- 左：ロゴ -->
    <a href="{{ $isAdminArea ? url('/admin') : url('/') }}" class="header__logo">
      <img src="{{ asset('images/logo.svg') }}" alt="COACHTECH" width="370" height="36">
    </a>

    {{-- 右：ナビ（必要に応じて出し分け／子ビューから差し替え可能） --}}
    @unless($hideNav)
      @if ($isAdminArea)
        {{-- 管理者ナビ：子ビューが @section('admin_nav') を用意していれば差し替え、無ければデフォルト --}}
        @hasSection('admin_nav')
          @yield('admin_nav')
        @else
          <nav class="admin-nav" aria-label="管理者ナビ">
            <ul class="admin-nav__list">
              <li><a href="{{ url('/admin/attendance/list') }}" class="admin-nav__link">勤怠一覧</a></li>
              <li><a href="{{ url('/admin/staff/list') }}"       class="admin-nav__link">スタッフ一覧</a></li>
              <li><a href="{{ url('/stamp_correction_request/list') }}" class="admin-nav__link">申請一覧</a></li>
              <li>
                <form action="{{ route('admin.logout') }}" method="POST" class="adminlogout-form">
                  @csrf
                  <button type="submit" class="admin-nav__link admin-nav__logout">ログアウト</button>
                </form>
              </li>
            </ul>
          </nav>
        @endif
      @else
        {{-- 一般ユーザーナビ：子ビューが @section('user_nav') を用意していれば差し替え、無ければデフォルト --}}
        @hasSection('user_nav')
          @yield('user_nav')
        @else
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
      @endif
    @endunless
  </div>
</header>

{{-- コンテンツ領域（クラス名もユーザー／管理者で切替可） --}}
<div class="{{ $isAdminArea ? 'content' : 'container' }}">
  @yield('content')
</div>

</body>
</html>
