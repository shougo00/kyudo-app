@extends('layouts.user')

@section('content')

<div class="system-admin-page">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">システム管理</h4>
            <div class="text-muted">KANRI専用の管理ダッシュボード</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('users.index') }}" class="btn btn-outline-primary">ユーザー管理</a>
            <a href="{{ route('groups') }}" class="btn btn-outline-primary">グループ管理</a>
            <a href="{{ route('news.index') }}" class="btn btn-outline-primary">お知らせ管理</a>
        </div>
    </div>

    <div class="admin-stat-grid mb-4">
        @foreach([
            '全ユーザー' => $stats['users'],
            '一般ユーザー' => $stats['normal_users'],
            '管理ユーザー' => $stats['admins'],
            'グループ' => $stats['groups'],
            '記録' => $stats['records'],
            '矢数' => $stats['shots'],
            '立順' => $stats['lineups'],
            '試合チーム' => $stats['match_teams'],
            '射形記録' => $stats['kyudo_results'],
            'お知らせ' => $stats['news'],
            'セッション' => $stats['sessions'],
            'ジョブ' => $stats['jobs'],
        ] as $label => $value)
            <div class="admin-stat">
                <span>{{ $label }}</span>
                <strong>{{ number_format($value) }}</strong>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="admin-panel">
                <div class="admin-panel-head">最近のユーザー</div>
                <div class="admin-list">
                    @forelse($recentUsers as $recentUser)
                        <div class="admin-list-row">
                            <div>
                                <strong>{{ $recentUser->name }}</strong>
                                <span>{{ $recentUser->username }}</span>
                            </div>
                            @if($recentUser->is_admin)
                                <span class="badge text-bg-success">管理</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">ユーザーがいません。</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-panel">
                <div class="admin-panel-head">最近のグループ</div>
                <div class="admin-list">
                    @forelse($recentGroups as $recentGroup)
                        <div class="admin-list-row group-row">
                            <div>
                                <strong>{{ $recentGroup->name }}</strong>
                                <span>ホスト：{{ $recentGroup->host?->name ?? '未設定' }} / {{ $recentGroup->users->count() }}人</span>
                            </div>
                            <div class="admin-row-actions">
                                <a href="/group/{{ $recentGroup->id }}/history" class="btn btn-sm btn-outline-secondary">履歴</a>
                                <a href="/group/{{ $recentGroup->id }}/records" class="btn btn-sm btn-outline-secondary">記録</a>
                                <a href="/group/{{ $recentGroup->id }}/attendance" class="btn btn-sm btn-outline-secondary">出席</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">グループがありません。</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-panel">
                <div class="admin-panel-head">最近の記録</div>
                <div class="admin-list">
                    @forelse($recentRecords as $recentRecord)
                        <div class="admin-list-row">
                            <div>
                                <strong>{{ $recentRecord->user?->name ?? '不明' }}</strong>
                                <span>{{ $recentRecord->date }} / {{ $recentRecord->practice_type }} / {{ $recentRecord->tate_no }}立</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">記録がありません。</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="admin-panel">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
            <div>
                <div class="admin-panel-head mb-0">ログ閲覧</div>
                <div class="text-muted small">storage/logs のログファイル末尾300行を表示します。</div>
            </div>
            @if($selectedLog)
                <div class="text-muted small">
                    {{ $selectedLog['name'] }} / {{ number_format($selectedLog['size'] / 1024, 1) }} KB / {{ $selectedLog['updated_at'] }}
                </div>
            @endif
        </div>

        @if(count($logFiles) > 0)
            <div class="log-tabs mb-2">
                @foreach($logFiles as $logFile)
                    <a href="{{ route('admin.system.index', ['log' => $logFile['name']]) }}"
                       class="{{ $selectedLog && $selectedLog['name'] === $logFile['name'] ? 'active' : '' }}">
                        {{ $logFile['name'] }}
                    </a>
                @endforeach
            </div>

            <pre class="admin-log-view">@forelse($logLines as $line){{ $line }}
@emptyログ内容はありません。
@endforelse</pre>
        @else
            <div class="alert alert-light border mb-0">ログファイルが見つかりません。</div>
        @endif
    </div>
</div>

<style>
.system-admin-page {
    padding-bottom: 24px;
}

.admin-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 10px;
}

.admin-stat,
.admin-panel {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
}

.admin-stat {
    padding: 12px;
}

.admin-stat span {
    display: block;
    color: #6c757d;
    font-size: 12px;
    font-weight: 700;
}

.admin-stat strong {
    display: block;
    margin-top: 4px;
    font-size: 24px;
    line-height: 1.1;
}

.admin-panel {
    padding: 14px;
    height: 100%;
}

.admin-panel-head {
    margin-bottom: 10px;
    font-weight: 800;
}

.admin-list {
    display: grid;
    gap: 9px;
}

.admin-list-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding-bottom: 9px;
    border-bottom: 1px solid #edf0f3;
}

.admin-list-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.admin-list-row strong,
.admin-list-row span {
    display: block;
}

.admin-list-row span {
    color: #6c757d;
    font-size: 12px;
}

.group-row {
    align-items: flex-start;
}

.admin-row-actions {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.log-tabs {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 4px;
}

.log-tabs a {
    flex: 0 0 auto;
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 6px 10px;
    color: #495057;
    background: #f8f9fa;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.log-tabs a.active {
    color: #fff;
    border-color: #0d6efd;
    background: #0d6efd;
}

.admin-log-view {
    max-height: 560px;
    overflow: auto;
    margin: 0;
    padding: 12px;
    border-radius: 8px;
    background: #111827;
    color: #e5e7eb;
    font-size: 12px;
    line-height: 1.55;
    white-space: pre-wrap;
    word-break: break-word;
}

@media (max-width: 600px) {
    .admin-row-actions {
        width: 100%;
        justify-content: stretch;
    }

    .admin-row-actions .btn {
        flex: 1;
    }
}
</style>

@endsection
