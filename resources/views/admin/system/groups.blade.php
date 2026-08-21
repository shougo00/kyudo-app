@extends('layouts.user')

@section('content')

<div class="system-admin-page">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">KANRIグループ管理</h4>
            <div class="text-muted">作成済みグループの確認、招待コード・最大人数の設定、削除ができます。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary">システム管理</a>
            <a href="{{ route('admin.system.users') }}" class="btn btn-outline-primary">ユーザー管理</a>
            <a href="{{ route('admin.system.license-codes') }}" class="btn btn-outline-primary">ライセンスコード管理</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">入力内容を確認してください。</div>
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
                    <th style="min-width: 180px;">最大人数</th>
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
                        <td>
                            <form method="POST"
                                  action="{{ route('admin.system.groups.update', $managedGroup) }}"
                                  class="admin-code-form">
                                @csrf
                                @method('PATCH')
                                <input type="text"
                                       name="invite_code"
                                       value="{{ old("groups.{$managedGroup->id}.invite_code", $managedGroup->invite_code) }}"
                                       class="form-control form-control-sm admin-code-input"
                                       maxlength="5"
                                       pattern="[A-Za-z0-9]{5}"
                                       placeholder="A1B2C"
                                       required>
                                <button type="submit" class="btn btn-sm btn-primary">保存</button>
                            </form>
                        </td>
                        <td>
                            {{ $managedGroup->users_count }}人
                            @if($managedGroup->max_members)
                                <span class="text-muted small d-block">/ {{ $managedGroup->max_members }}人</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST"
                                  action="{{ route('admin.system.groups.update', $managedGroup) }}"
                                  class="admin-limit-form">
                                @csrf
                                @method('PATCH')
                                <input type="number"
                                       name="max_members"
                                       value="{{ $managedGroup->max_members }}"
                                       class="form-control form-control-sm"
                                       min="1"
                                       max="999"
                                       placeholder="無制限">
                                <button type="submit" class="btn btn-sm btn-primary">保存</button>
                            </form>
                        </td>
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
                        <td colspan="8" class="text-muted text-center py-4">グループが見つかりません。</td>
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

.admin-code-form,
.admin-limit-form {
    display: grid;
    grid-template-columns: minmax(80px, 1fr) auto;
    gap: 6px;
}

.admin-code-input {
    text-transform: uppercase;
}

.admin-actions form {
    margin: 0;
}

@media (max-width: 600px) {
    .admin-search {
        grid-template-columns: 1fr;
    }

    .admin-actions .btn,
    .admin-actions form,
    .admin-code-form,
    .admin-code-form .btn,
    .admin-limit-form,
    .admin-limit-form .btn {
        width: 100%;
    }

    .admin-code-form,
    .admin-limit-form {
        grid-template-columns: 1fr;
    }
}
</style>

@endsection
