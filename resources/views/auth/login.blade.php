<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="manifest" href="/manifest.json">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="theme-color" content="#2f5d46">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MATOWA">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <title>ログイン｜MATOWA</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<style>
html, body {
    overscroll-behavior: none;
    touch-action: manipulation;
    height: 100%;
}

body {
    background: linear-gradient(135deg, #f6f1e7, #e4dccf);
    font-family: system-ui, -apple-system, "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
}

.login-wrapper {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.card {
    width: 100%;
    max-width: 420px;
    border-radius: 18px;
    border: none;
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    background: #ffffffee;
}

.header-area {
    text-align: center;
    padding: 25px 20px 10px;
}

.kyudo-icon {
    font-size: 36px;
    margin-bottom: 8px;
}

.title {
    font-size: 22px;
    font-weight: bold;
    letter-spacing: 1px;
}

.subtitle {
    font-size: 13px;
    color: #777;
}

.card-body {
    padding: 25px;
}

.form-label {
    font-weight: 600;
}

.form-control {
    border-radius: 10px;
    padding: 10px;
}

.form-control:focus {
    border-color: #2f5d46;
    box-shadow: 0 0 0 0.2rem rgba(47,93,70,0.15);
}

.btn-primary {
    background-color: #2f5d46;
    border: none;
    border-radius: 12px;
    padding: 10px;
    font-weight: bold;
}

.btn-primary:hover {
    background-color: #3f7a60;
}

.btn-outline-primary {
    border-color: #2f5d46;
    color: #2f5d46;
    border-radius: 10px;
}

.btn-outline-primary:hover {
    background-color: #2f5d46;
    color: #fff;
}
</style>

<script>
window.addEventListener('pageshow', function (event) {
    const navigation = performance.getEntriesByType('navigation')[0];

    if (event.persisted || navigation?.type === 'back_forward') {
        window.location.reload();
    }
});
</script>

<body>

<div class="login-wrapper">

    <div class="card">

        <!-- ヘッダー -->
        <div class="header-area">
            <div class="title">MATOWA</div>
            <div class="subtitle">的中・出欠管理</div>
        </div>

        <div class="card-body">

            @if(session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <!-- ユーザー名 -->
                <div class="mb-3">
                    <label for="login" class="form-label">ユーザー名</label>
                    <input id="login"
                        type="text"
                        name="login"
                        class="form-control @error('login') is-invalid @enderror"
                        value="{{ old('login') }}"
                        required
                        autofocus>
                </div>

                <!-- パスワード -->
                <div class="mb-3">
                    <label for="password" class="form-label">パスワード</label>
                    <input id="password"
                        type="password"
                        class="form-control @error('password') is-invalid @enderror"
                        name="password"
                        required>
                </div>

                <!-- ログイン保持 -->
                <div class="form-check mb-3">
                    <input class="form-check-input"
                        type="checkbox"
                        name="remember"
                        id="remember_me"
                        {{ old('remember', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember_me">
                        ログイン状態を保持する
                    </label>
                </div>

                <!-- ログインボタン -->
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary">
                        ログイン
                    </button>
                </div>

                <hr>

                <!-- 新規登録 -->
                <div class="text-center">
                    <div class="small mb-2">アカウントをお持ちでないですか？</div>
                    <a href="{{ route('register') }}" class="btn btn-outline-primary btn-sm px-4">
                        新規登録
                    </a>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
