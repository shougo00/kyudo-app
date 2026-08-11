<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
@include('layouts.partials.app-icons')

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>MATOWA</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* PC ロゴとアバター間の間隔 */
.navbar-brand { display:flex; align-items:center; gap:0.3rem; }

.navbar-avatar-link {
    display: inline-flex;
    align-items: center;
    line-height: 0;
}

.system-brand-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    object-fit: contain;
}

/* ----------------------
   スマホ対応
---------------------- */
@media (max-width: 991px) {
    /* アバター + FAMLEVEL 中央揃え */
    .navbar-mobile-center {
        display:flex;
        align-items:center;
        justify-content:center;
        gap:0.3rem;
        flex-shrink:0;
        line-height:1;
    }

    .navbar-mobile-center span {
        white-space: nowrap;
    }

    /* レベル・ポイント表示を小さく */
    .nav-item.d-flex .badge { font-size:0.7rem; }
    .nav-item.d-flex .progress { width:80px; height:8px; }

    /* ナビメニュー文字を小さく */
    .navbar-nav .nav-link { font-size:0.9rem; }

    .system-brand-icon {
        width: 34px;
        height: 34px;
    }
}

.nav-unread-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #dc3545;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
}

.hamburger-unread-badge {
    position: absolute;
    top: 0;
    right: 0;
    transform: translate(35%, -35%);
}
</style>
</head>
<body class="bg-light">
@auth
    @php
        $unreadNewsCount = Auth::user()->unreadNewsCount();
        $unreadNewsLabel = $unreadNewsCount > 99 ? '99+' : (string) $unreadNewsCount;
    @endphp
@endauth

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
<div class="container d-flex justify-content-between align-items-center">

    {{-- PC用 --}}
    <div class="d-none d-lg-flex align-items-center">
        @auth
            @php $avatar = Auth::user()->avatar; @endphp
            <a href="{{ route('avatar.show') }}" class="navbar-avatar-link me-2">
                @include('avatar.partials.stack', ['avatar' => $avatar, 'scale' => 0.115])
            </a>
        @endauth

        <img src="{{ asset('icons/app-icon.png') }}" alt="" class="system-brand-icon me-2">
        <span class="fw-bold text-dark">MATOWA</span>
    </div>


    {{-- スマホ用 --}}
    <div class="d-flex d-lg-none w-100 justify-content-between align-items-center">

        <!-- 左 -->
        <img src="{{ asset('icons/app-icon.png') }}" alt="" class="system-brand-icon">

        <!-- 中央 -->
        <div class="navbar-mobile-center">
            @auth
                @php $avatar = Auth::user()->avatar; @endphp
                <a href="{{ route('avatar.show') }}" class="navbar-avatar-link">
                    @include('avatar.partials.stack', ['avatar' => $avatar, 'scale' => 0.1])
                </a>
            @endauth
            <span class="fw-bold text-dark ms-1">MATOWA</span>
        </div>

        <!-- 右（ハンバーガー） -->
        <button class="navbar-toggler position-relative" type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileMenu">
            <span class="navbar-toggler-icon"></span>
            @auth
                @if($unreadNewsCount > 0)
                    <span class="nav-unread-badge hamburger-unread-badge">{{ $unreadNewsLabel }}</span>
                @endif
            @endauth
        </button>
    </div>


    {{-- PCメニュー --}}
    <div class="collapse navbar-collapse d-none d-lg-flex">
         <ul class="navbar-nav me-auto">
            @auth
                @php
                    $firstGroup = Auth::user()->groups->first();
                    $canViewGroupRecords = $firstGroup && (
                        (int) $firstGroup->host_user_id === (int) Auth::id()
                        || Auth::user()->username === 'KANRI'
                        || (bool) $firstGroup->show_group_records_to_members
                    );
                    $canViewGroupSelfRecords = $firstGroup && (
                        (int) $firstGroup->host_user_id === (int) Auth::id()
                        || Auth::user()->username === 'KANRI'
                    );
                @endphp

                @if(Auth::user()->username === 'KANRI')
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('admin.system.index') }}">システム管理</a>
                    </li>
                @endif

                {{-- 管理者じゃない人（＝通常ユーザー）は全部表示 --}}
                @if(!Auth::user()->is_admin)
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('home') }}">的中記録</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('dashboard') }}">的中履歴</a>
                    </li>
                @endif

                @if($canViewGroupRecords)
                    <li class="nav-item">
                        <a class="nav-link active" href="#"
                        onclick="goGroupRecord()">グループ 的中記録（正規連）</a>
                    </li>
                @endif

                @if($canViewGroupSelfRecords)
                    <li class="nav-item">
                        <a class="nav-link active" href="#"
                        onclick="goGroupSelfRecord()">グループ的中記録（自主練）</a>
                    </li>
                @endif

                <li class="nav-item">
                    <a class="nav-link active" href="#"
                    onclick="goGroupHistory()">グループ 的中履歴</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('groups') }}">
                        グループ設定
                    </a>
                </li>

                {{-- 👇 ここから通常ユーザーのみ --}}
                @if(!Auth::user()->is_admin)

                    <li class="nav-item">
                        <a class="nav-link active" href="#"
                        onclick="goAttendance()">出欠確認</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link active d-inline-flex align-items-center gap-1" href="{{ route('news.index') }}">
                            お知らせ
                            @if($unreadNewsCount > 0)
                                <span class="nav-unread-badge">{{ $unreadNewsLabel }}</span>
                            @endif
                        </a>
                    </li>

                    @if(Auth::user()->uses_camera)
                        <li class="nav-item">
                            <a class="nav-link active" href="{{ route('camera') }}">カメラ</a>
                        </li>
                    @endif

                @endif

                @if(Auth::user()->is_admin)
                    <li class="nav-item">
                        <a class="nav-link active" href="#"
                        onclick="goAttendance()">出席管理</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link active d-inline-flex align-items-center gap-1" href="{{ route('news.index') }}">
                            お知らせ
                            @if($unreadNewsCount > 0)
                                <span class="nav-unread-badge">{{ $unreadNewsLabel }}</span>
                            @endif
                        </a>
                    </li>
                @endif

            @endauth
        </ul>

        <ul class="navbar-nav ms-auto align-items-center">
            @auth
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        {{ Auth::user()->name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}">プロフィール</a></li>
                        <li><a class="dropdown-item" href="{{ route('settings.index') }}">設定</a></li>
                    </ul>
                </li>
            @else
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('login') }}">ログイン</a>
                </li>
            @endauth
        </ul>
    </div>

</div>

{{-- 🔥 スマホ用サイドメニュー --}}
<div class="offcanvas offcanvas-end d-lg-none" style="width: 50%;" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">メニュー</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">

        @auth
            @php $avatar = Auth::user()->avatar; @endphp

            <div class="text-center mb-3">
                @include('avatar.partials.stack', ['avatar' => $avatar, 'scale' => 0.13])

                <div class="mt-2 fw-bold">{{ Auth::user()->name }}</div>
            </div>
        @endauth

       <ul class="navbar-nav">

            @auth
                @php
                    $firstGroup = Auth::user()->groups->first();
                    $canViewGroupRecords = $firstGroup && (
                        (int) $firstGroup->host_user_id === (int) Auth::id()
                        || Auth::user()->username === 'KANRI'
                        || (bool) $firstGroup->show_group_records_to_members
                    );
                    $canViewGroupSelfRecords = $firstGroup && (
                        (int) $firstGroup->host_user_id === (int) Auth::id()
                        || Auth::user()->username === 'KANRI'
                    );
                @endphp

                @if(Auth::user()->username === 'KANRI')
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="{{ route('admin.system.index') }}">
                            システム管理
                        </a>
                    </li>
                @endif

                {{-- 管理者じゃない人だけ --}}
                @if(!Auth::user()->is_admin)
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="{{ route('home') }}">的中記録</a>
                    </li>

                    <li class="nav-item mb-2">
                        <a class="nav-link" href="{{ route('dashboard') }}">的中履歴</a>
                    </li>
                @endif

                @if($canViewGroupRecords)
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" onclick="goGroupRecord()">
                            グループ的中記録（正規連）
                        </a>
                    </li>
                @endif

                @if($canViewGroupSelfRecords)
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" onclick="goGroupSelfRecord()">
                            グループ的中記録（自主練）
                        </a>
                    </li>
                @endif

                <li class="nav-item mb-2">
                    <a class="nav-link" href="#" onclick="goGroupHistory()">
                        グループ 的中履歴
                    </a>
                </li>

                <li class="nav-item mb-2">
                    <a class="nav-link" href="{{ route('groups') }}">
                        グループ設定
                    </a>
                </li>

                {{-- 👇 一般ユーザーだけ表示 --}}
                @if(!Auth::user()->is_admin)

                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" onclick="goAttendance()">
                            出欠確認
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a class="nav-link d-inline-flex align-items-center gap-2" href="{{ route('news.index') }}">
                            お知らせ
                            @if($unreadNewsCount > 0)
                                <span class="nav-unread-badge">{{ $unreadNewsLabel }}</span>
                            @endif
                        </a>
                    </li>

                    @if(Auth::user()->uses_camera)
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="{{ route('camera') }}">
                                カメラ
                            </a>
                        </li>
                    @endif

                @endif

                @if(Auth::user()->is_admin)
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" onclick="goAttendance()">
                            出席管理
                        </a>
                    </li>

                    <li class="nav-item mb-2">
                        <a class="nav-link d-inline-flex align-items-center gap-2" href="{{ route('news.index') }}">
                            お知らせ
                            @if($unreadNewsCount > 0)
                                <span class="nav-unread-badge">{{ $unreadNewsLabel }}</span>
                            @endif
                        </a>
                    </li>
                @endif

                <li class="nav-item mb-2">
                    <a class="nav-link" href="{{ route('avatar.show') }}">アバター</a>
                </li>

                <li class="nav-item mb-2">
                    <a class="nav-link" href="{{ route('profile.edit') }}">プロフィール</a>
                </li>

                <li class="nav-item mb-2">
                    <a class="nav-link" href="{{ route('settings.index') }}">設定</a>
                </li>

            @else
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('login') }}">ログイン</a>
                </li>
            @endauth

        </ul>
    </div>
</div>
</nav>

{{-- ページヘッダー --}}
@isset($header)
<header class="bg-white shadow py-3 mb-4">
    <div class="container">
        <h1 class="h4 m-0 text-dark">{{ $header }}</h1>
    </div>
</header>
@endisset

<main class="container mb-5">
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
<script>

function goGroupHistory() {

    @auth
        let groupId = {{ Auth::user()->groups->first()->id ?? 'null' }};

        if (!groupId) {
            alert('グループに参加していません');
            return;
        }

        // 👇ここが履歴ページ
        window.location.href = `/group/${groupId}/history`;

    @else
        alert('ログインしてください');
    @endauth

}

function goAttendance() {

    @auth
        let groupId = {{ Auth::user()->groups->first()->id ?? 'null' }};

        if (!groupId) {
            alert('グループに参加していません');
            return;
        }

        window.location.href = `/group/${groupId}/attendance`;

    @else
        alert('ログインしてください');
    @endauth
}

function goGroupRecord() {

    @auth
        let groupId = {{ Auth::user()->groups->first()->id ?? 'null' }};

        if (!groupId) {
            alert('グループに参加していません');
            return;
        }

        // 遷移
        window.location.href = `/group/${groupId}/records`;

    @else
        alert('ログインしてください');
    @endauth

}

function goGroupSelfRecord() {

    @auth
        let groupId = {{ Auth::user()->groups->first()->id ?? 'null' }};

        if (!groupId) {
            alert('グループに参加していません');
            return;
        }

        window.location.href = `/group/${groupId}/self-records`;

    @else
        alert('ログインしてください');
    @endauth

}

</script>
</html>
