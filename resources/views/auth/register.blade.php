<!DOCTYPE html>
<html lang="ja">
<head>
    <link rel="manifest" href="/manifest.json">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2f5d46">

    <title>新規登録｜MATOWA</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<style>
html, body {
    height: 100%;
    overscroll-behavior: none;
    touch-action: manipulation;
}

body {
    background: linear-gradient(135deg, #f6f1e7, #e4dccf);
    font-family: system-ui, -apple-system, "Yu Gothic", sans-serif;
}

.register-wrapper {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.card {
    width: 100%;
    max-width: 430px;
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
    font-size: 34px;
    margin-bottom: 6px;
}

.title {
    font-size: 22px;
    font-weight: bold;
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

.radio-group {
    display: flex;
    gap: 20px;
}
</style>

<body>

<div class="register-wrapper">

    <div class="card">

        <!-- ヘッダー -->
        <div class="header-area">
            <div class="title">新規ユーザー登録</div>
            <div class="subtitle">MATOWAを始める</div>
        </div>

        <div class="card-body">

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <!-- 表示名 -->
                <div class="mb-3">
                    <label class="form-label">表示名（ニックネーム）</label>
                    <input type="text"
                        name="name"
                        class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name') }}"
                        required>
                </div>

                <!-- ユーザー名 -->
                <div class="mb-3">
                    <label class="form-label">ユーザー名（ログイン時に使用）</label>
                    <input type="text"
                        name="username"
                        class="form-control @error('username') is-invalid @enderror"
                        value="{{ old('username') }}"
                        pattern="[A-Za-z0-9]{5,}"
                        title="ユーザー名は英数字5文字以上で入力してください"
                        required>
                </div>

                <!-- パスワード -->
                <div class="mb-3">
                    <label class="form-label">パスワード</label>
                    <input type="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        required>
                </div>

                <!-- 確認 -->
                <div class="mb-3">
                    <label class="form-label">パスワード（確認）</label>
                    <input type="password"
                        name="password_confirmation"
                        class="form-control"
                        required>
                </div>

                <!-- 試合区分 -->
                <div class="mb-3">
                    <label class="form-label">試合区分</label>

                    <div class="radio-group">
                        <label>
                            <input type="radio" name="gender" value="male"
                                {{ old('gender') == 'male' ? 'checked' : '' }} required>
                            男子の部
                        </label>

                        <label>
                            <input type="radio" name="gender" value="female"
                                {{ old('gender') == 'female' ? 'checked' : '' }}>
                            女子の部
                        </label>
                    </div>
                </div>

                <!-- ライセンスコード -->
                <div class="mb-3">
                    <label class="form-label">ライセンスコード</label>
                    <input type="text"
                        name="license_code"
                        class="form-control @error('license_code') is-invalid @enderror"
                        value="{{ old('license_code') }}"
                        maxlength="50"
                        required>
                </div>

                <!-- 登録ボタン -->
                <div class="d-grid mb-3">
                    <button class="btn btn-primary">
                        登録する
                    </button>
                </div>

                <!-- ログインリンク -->
                <div class="text-center">
                    <a href="{{ route('login') }}" class="small text-decoration-none">
                        すでにアカウントをお持ちですか？
                    </a>
                </div>

            </form>

        </div>
    </div>

</div>

</body>
</html>
