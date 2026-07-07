@extends('layouts.user')

@section('content')

<div class="system-admin-page">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">KANRIグループ管理</h4>
            <div class="text-muted">作成済みグループの確認と削除ができます。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary">システム管理</a>
            <a href="{{ route('admin.system.users') }}" class="btn btn-outline-primary">ユーザー管理</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.system.groups') }}" class="admin-search mb-3">
        <input type="search"
               name="search"
               value="{{ $search }}"
               class="form-control"
               placeholder="グループ名・招待コード・ホスト名で検索">
        <button type="submit" class="btn btn-dark">検索</button>
    </form>

    <div class="table-responsive admin-table-wrap">
        <table class="table table-bordered table-striped align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>グループ名</th>
                    <th>ホスト</th>
                    <th>招待コード</th>
                    <th>人数</th>
                    <th>作成日</th>
                    <th style="min-width: 260px;">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groups as $managedGroup)
                    <tr>
                        <td>{{ $managedGroup->id }}</td>
                        <td>
                            <strong>{{ $managedGroup->name }}</strong>
                        </td>
                        <td>
                            @if($managedGroup->host)
                                {{ $managedGroup->host->name }}
                                <span class="text-muted small d-block">{{ $managedGroup->host->username }}</span>
                            @else
                                <span class="text-muted">未設定</span>
                            @endif
                        </td>
                        <td>{{ $managedGroup->invite_code }}</td>
                        <td>{{ $managedGroup->users_count }}人</td>
                        <td>{{ $managedGroup->created_at?->format('Y-m-d') }}</td>
                        <td>
                            <div class="admin-actions">
                                <a href="{{ route('group.history', $managedGroup) }}" class="btn btn-sm btn-outline-secondary">履歴</a>
                                <a href="{{ route('group.records', $managedGroup->id) }}" class="btn btn-sm btn-outline-secondary">記録</a>
                                <a href="/group/{{ $managedGroup->id }}/attendance" class="btn btn-sm btn-outline-secondary">出席</a>
                                <form method="POST" action="{{ route('admin.system.groups.destroy', $managedGroup) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('{{ $managedGroup->name }} を削除します。所属メンバーは全員退会状態になり、グループの立順や試合チームも削除されます。よろしいですか？')">
                                        削除
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted text-center py-4">グループが見つかりません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $groups->links() }}
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

.admin-table-wrap {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
}

.admin-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.admin-actions form {
    margin: 0;
}

@media (max-width: 600px) {
    .admin-search {
        grid-template-columns: 1fr;
    }

    .admin-actions .btn,
    .admin-actions form {
        width: 100%;
    }
}
</style>

@endsection
