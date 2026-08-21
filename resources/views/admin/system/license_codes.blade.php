@extends('layouts.user')

@section('content')

<div class="system-admin-page">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">ライセンスコード管理</h4>
            <div class="text-muted">新規登録時に必要なコードを管理します。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary">システム管理</a>
            <a href="{{ route('admin.system.users') }}" class="btn btn-outline-primary">ユーザー管理</a>
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
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="admin-card mb-3">
        <form method="POST" action="{{ route('admin.system.license-codes.store') }}" class="license-create-form">
            @csrf
            <label>
                <span>ライセンスコード</span>
                <input type="text"
                       name="code"
                       value="{{ old('code') }}"
                       class="form-control license-code-input"
                       maxlength="50"
                       pattern="[A-Za-z0-9-]+"
                       required>
            </label>
            <label>
                <span>メモ</span>
                <input type="text"
                       name="memo"
                       value="{{ old('memo') }}"
                       class="form-control"
                       maxlength="255"
                       placeholder="配布先や用途">
            </label>
            <label>
                <span>紐づけグループ</span>
                <select name="group_id" class="form-select">
                    <option value="">なし</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" {{ (string) old('group_id') === (string) $group->id ? 'selected' : '' }}>
                            {{ $group->name }}（{{ $group->invite_code }}）
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="admin-check">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>有効</span>
            </label>
            <button type="submit" class="btn btn-primary">追加</button>
        </form>
    </div>

    <form method="GET" action="{{ route('admin.system.license-codes') }}" class="admin-search mb-3">
        <input type="search"
               name="search"
               value="{{ $search }}"
               class="form-control"
               placeholder="コード・メモで検索">
        <button type="submit" class="btn btn-dark">検索</button>
    </form>

    <div class="admin-list">
        @forelse($licenseCodes as $licenseCode)
            <div class="admin-card">
                <form method="POST"
                      action="{{ route('admin.system.license-codes.update', $licenseCode) }}"
                      class="license-row-form">
                    @csrf
                    @method('PATCH')

                    <div class="admin-card-head">
                        <div>
                            <strong>{{ $licenseCode->code }}</strong>
                            <span>
                                ID: {{ $licenseCode->id }}
                                / 使用: {{ $licenseCode->users_count }}人
                                / 作成: {{ $licenseCode->created_at?->format('Y-m-d') }}
                                @if($licenseCode->creator)
                                    / 作成者: {{ $licenseCode->creator->name }}
                                @endif
                                @if($licenseCode->group)
                                    / グループ: {{ $licenseCode->group->name }}
                                @endif
                            </span>
                        </div>
                        <span class="badge {{ $licenseCode->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $licenseCode->is_active ? '有効' : '無効' }}
                        </span>
                    </div>

                    <div class="admin-form-grid">
                        <label>
                            <span>コード</span>
                            <input type="text"
                                   name="code"
                                   value="{{ old("license_codes.{$licenseCode->id}.code", $licenseCode->code) }}"
                                   class="form-control license-code-input"
                                   maxlength="50"
                                   pattern="[A-Za-z0-9-]+"
                                   required>
                        </label>
                        <label>
                            <span>メモ</span>
                            <input type="text"
                                   name="memo"
                                   value="{{ old("license_codes.{$licenseCode->id}.memo", $licenseCode->memo) }}"
                                   class="form-control"
                                   maxlength="255">
                        </label>
                        <label>
                            <span>紐づけグループ</span>
                            <select name="group_id" class="form-select">
                                <option value="">なし</option>
                                @foreach($groups as $group)
                                    @php
                                        $selectedGroupId = old("license_codes.{$licenseCode->id}.group_id", old('group_id', $licenseCode->group_id));
                                    @endphp
                                    <option value="{{ $group->id }}" {{ (string) $selectedGroupId === (string) $group->id ? 'selected' : '' }}>
                                        {{ $group->name }}（{{ $group->invite_code }}）
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="admin-check">
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   {{ $licenseCode->is_active ? 'checked' : '' }}>
                            <span>有効</span>
                        </label>
                    </div>

                    <div class="admin-card-actions">
                        <button type="submit" class="btn btn-primary">更新</button>
                    </div>
                </form>

                <form method="POST"
                      action="{{ route('admin.system.license-codes.destroy', $licenseCode) }}"
                      class="admin-delete-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn btn-outline-danger"
                            {{ $licenseCode->users_count > 0 ? 'disabled' : '' }}
                            onclick="return confirm('{{ $licenseCode->code }} を削除します。よろしいですか？')">
                        削除
                    </button>
                </form>
            </div>
        @empty
            <div class="alert alert-light border">ライセンスコードが見つかりません。</div>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $licenseCodes->links() }}
    </div>
</div>

<style>
.system-admin-page {
    padding-bottom: 24px;
}

.admin-search,
.license-create-form {
    display: grid;
    grid-template-columns: minmax(150px, 0.7fr) minmax(180px, 1fr) minmax(180px, 1fr) auto auto;
    gap: 8px;
    align-items: end;
}

.admin-search {
    grid-template-columns: minmax(0, 1fr) auto;
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
.license-create-form label > span,
.admin-form-grid label > span,
.admin-check span {
    display: block;
}

.admin-card-head span,
.license-create-form label > span,
.admin-form-grid label > span,
.admin-check span {
    color: #6c757d;
    font-size: 12px;
    font-weight: 700;
}

.admin-form-grid {
    display: grid;
    grid-template-columns: minmax(160px, 0.7fr) minmax(180px, 1fr) minmax(180px, 1fr) auto;
    gap: 10px;
    align-items: end;
}

.admin-check {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 38px;
}

.license-code-input {
    text-transform: uppercase;
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

@media (max-width: 700px) {
    .admin-search,
    .license-create-form,
    .admin-form-grid {
        grid-template-columns: 1fr;
    }

    .admin-card-head {
        display: block;
    }

    .admin-card-actions,
    .admin-delete-form,
    .admin-card-actions .btn,
    .admin-delete-form .btn {
        display: grid;
        width: 100%;
        margin-left: 0;
    }
}
</style>

@endsection
