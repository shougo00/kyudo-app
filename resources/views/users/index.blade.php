<x-app-layout>
    <div class="container py-3 py-md-5">

        <h1 class="mb-3 mb-md-4 fs-4 fs-md-2">ユーザ一覧</h1>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <!-- スマホ横スクロール対応 -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle text-nowrap">
                <thead class="table-dark">
                    <tr>
                        <th class="d-none d-md-table-cell">ID</th>
                        <th>名前</th>
                        <th class="d-none d-md-table-cell">メール</th>
                        <th>権限</th>
                        <th style="min-width: 140px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr class="{{ $user->is_admin ? 'table-warning' : '' }}">
                        <td class="d-none d-md-table-cell">{{ $user->id }}</td>
                        <td>{{ $user->name }}</td>
                        <td class="d-none d-md-table-cell">{{ $user->email }}</td>
                        <td>
                            @if($user->is_admin)
                                <span class="badge bg-success">管理者</span>
                            @else
                                <span class="badge bg-secondary">一般</span>
                            @endif
                        </td>
                        <td>
                            <!-- スマホは縦並び -->
                            <div class="d-grid gap-2 d-md-flex">
                                <form action="{{ route('users.reset-password', $user) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm w-100"
                                        onclick="return confirm('このユーザーのパスワードをリセットしますか？')">
                                        リセット
                                    </button>
                                </form>

                                <form action="{{ route('users.destroy', $user) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm w-100"
                                        onclick="return confirm('削除しますか？')">
                                        削除
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </div>

    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @endpush
</x-app-layout>
