<x-app-layout>
    <div class="container py-5">

        <!-- アカウント情報の更新 -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">アカウント情報の更新7</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    アカウント情報やメールアドレスを更新できます。
                </p>
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <!-- パスワード変更 -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">パスワードの変更</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    安全のため、長くランダムなパスワードを使用してください。
                </p>
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <!-- アカウント削除 -->
        <div class="card mb-4 shadow-sm border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">アカウント削除</h5>
            </div>
            <div class="card-body">
                <p class="text-danger mb-3">
                    アカウントを削除すると、すべてのデータが完全に削除されます。  
                    この操作は取り消せません。
                </p>
                @include('profile.partials.delete-user-form')
            </div>
        </div>

    </div>

    <!-- Bootstrap CSS/JS -->
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @endpush
</x-app-layout>
