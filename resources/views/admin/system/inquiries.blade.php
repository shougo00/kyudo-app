@extends('layouts.user')

@section('content')

<div class="system-admin-page">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">お問い合わせ管理</h4>
            <div class="text-muted">トップページから送信されたお問い合わせ情報を確認します。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary">システム管理</a>
            <a href="{{ route('admin.system.users') }}" class="btn btn-outline-primary">ユーザー管理</a>
            <a href="{{ route('admin.system.groups') }}" class="btn btn-outline-primary">グループ管理</a>
            <a href="{{ route('admin.system.license-codes') }}" class="btn btn-outline-primary">ライセンスコード管理</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.system.inquiries') }}" class="admin-search mb-3">
        <input type="search"
               name="search"
               value="{{ $search }}"
               class="form-control"
               placeholder="団体名・代表者名・メールで検索">
        <button type="submit" class="btn btn-dark">検索</button>
    </form>

    <div class="admin-list">
        @forelse($inquiries as $inquiry)
            <article class="admin-card">
                <div class="admin-card-head">
                    <div>
                        <strong>{{ $inquiry->group_name }}</strong>
                        <span>
                            ID: {{ $inquiry->id }}
                            / 送信: {{ $inquiry->created_at?->format('Y-m-d H:i') }}
                        </span>
                    </div>
                </div>

                <dl class="inquiry-meta">
                    <div>
                        <dt>代表者</dt>
                        <dd>{{ $inquiry->representative_name }}</dd>
                    </div>
                    <div>
                        <dt>メール</dt>
                        <dd><a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a></dd>
                    </div>
                </dl>

            </article>
        @empty
            <div class="alert alert-light border">お問い合わせは見つかりません。</div>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $inquiries->links() }}
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
    align-items: end;
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
.admin-card-head span {
    display: block;
}

.admin-card-head span,
.inquiry-meta dt {
    color: #6c757d;
    font-size: 12px;
    font-weight: 700;
}

.inquiry-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin: 0 0 12px;
}

.inquiry-meta dt,
.inquiry-meta dd {
    margin: 0;
}

.inquiry-meta dd {
    font-weight: 700;
    overflow-wrap: anywhere;
}

@media (max-width: 600px) {
    .admin-search,
    .inquiry-meta {
        grid-template-columns: 1fr;
    }

    .admin-search .btn {
        width: 100%;
    }
}
</style>

@endsection
