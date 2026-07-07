@extends('layouts.user')

@section('content')

<div class="system-admin-page">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">KANRIユーザー管理</h4>
            <div class="text-muted">表示名・ユーザー名・パスワード変更、アカウント削除ができます。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary">システム管理</a>
            <a href="{{ route('admin.system.groups') }}" class="btn btn-outline-primary">グループ管理</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            入力内容を確認してください。
        </div>
    @endif

    <form method="GET" action="{{ route('admin.system.users') }}" class="admin-search mb-3">
        <input type="search"
               name="search"
               value="{{ $search }}"
               class="form-control"
               placeholder="名前・ユーザー名で検索">
        <button type="submit" class="btn btn-dark">検索</button>
    </form>

    <div class="admin-list">
        @forelse($users as $managedUser)
            <div class="admin-card">
                <form method="POST" action="{{ route('admin.system.users.update', $managedUser) }}" class="admin-user-form">
                    @csrf
                    @method('PATCH')

                    <div class="admin-card-head">
                        <div>
                            <strong>{{ $managedUser->name }}</strong>
                            <span>ID: {{ $managedUser->id }} / 所属グループ: {{ $managedUser->groups_count }}件</span>
                        </div>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            @if($managedUser->username === 'KANRI')
                                <span class="badge text-bg-danger">KANRI</span>
                            @elseif($managedUser->is_admin)
                                <span class="badge text-bg-success">管理</span>
                            @else
                                <span class="badge text-bg-secondary">一般</span>
                            @endif
                        </div>
                    </div>

                    <div class="admin-form-grid">
                        <label>
                            <span>表示名</span>
                            <input type="text" name="name" value="{{ old("users.{$managedUser->id}.name", $managedUser->name) }}" class="form-control" required>
                        </label>
                        <label>
                            <span>ユーザー名</span>
                            <input type="text"
                                   name="username"
                                   value="{{ old("users.{$managedUser->id}.username", $managedUser->username) }}"
                                   class="form-control"
                                   {{ $managedUser->username === 'KANRI' ? 'readonly' : '' }}
                                   required>
                        </label>
                        <label>
                            <span>パスワード変更</span>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                        </label>
                    </div>

                    <div class="admin-card-actions">
                        <button type="submit" class="btn btn-primary">更新</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('admin.system.users.destroy', $managedUser) }}" class="admin-delete-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn btn-outline-danger"
                            {{ $managedUser->username === 'KANRI' ? 'disabled' : '' }}
                            onclick="return confirm('{{ $managedUser->name }} のアカウントを削除します。主催グループがある場合はそのグループも削除されます。よろしいですか？')">
                        アカウント削除
                    </button>
                </form>
            </div>
        @empty
            <div class="alert alert-light border">ユーザーが見つかりません。</div>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $users->links() }}
    </div>
</div>

<style>
.system-admin-page {
    padding-bottom: 24px;
}

.admin-search {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
}

.admin-list {
    display: grid;
    gap: 12px;
}

.admin-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
    padding: 14px;
}

.admin-card-head {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}

.admin-card-head strong,
.admin-card-head span,
.admin-form-grid label > span {
    display: block;
}

.admin-card-head span,
.admin-form-grid label > span {
    color: #6c757d;
    font-size: 12px;
    font-weight: 700;
}

.admin-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
    align-items: end;
}

.admin-card-actions,
.admin-delete-form {
    margin-top: 12px;
}

.admin-card-actions {
    display: inline-flex;
}

.admin-delete-form {
    display: inline-flex;
    margin-left: 8px;
}

@media (max-width: 600px) {
    .admin-search {
        grid-template-columns: 1fr;
    }

    .admin-card-head {
        display: block;
    }

    .admin-card-actions,
    .admin-delete-form {
        display: grid;
        width: 100%;
        margin-left: 0;
    }
}
</style>

@endsection
