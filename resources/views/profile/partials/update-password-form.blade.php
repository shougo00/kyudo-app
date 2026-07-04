<section class="container mt-5">
   

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="form-label">現在のパスワード</label>
            <input type="password" class="form-control" id="update_password_current_password" name="current_password" autocomplete="current-password">
            @if($errors->updatePassword->get('current_password'))
                <div class="text-danger mt-1">
                    {{ $errors->updatePassword->first('current_password') }}
                </div>
            @endif
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="form-label">新しいパスワード</label>
            <input type="password" class="form-control" id="update_password_password" name="password" autocomplete="new-password">
            @if($errors->updatePassword->get('password'))
                <div class="text-danger mt-1">
                    {{ $errors->updatePassword->first('password') }}
                </div>
            @endif
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="form-label">パスワード確認</label>
            <input type="password" class="form-control" id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password">
            @if($errors->updatePassword->get('password_confirmation'))
                <div class="text-danger mt-1">
                    {{ $errors->updatePassword->first('password_confirmation') }}
                </div>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">保存</button>

            @if (session('status') === 'password-updated')
                <p id="savedMessage" class="text-muted mb-0" style="transition: opacity 0.5s;">保存しました。</p>
                <script>
                    setTimeout(() => {
                        document.getElementById('savedMessage').style.opacity = 0;
                    }, 2000);
                </script>
            @endif
        </div>
    </form>
</section>
