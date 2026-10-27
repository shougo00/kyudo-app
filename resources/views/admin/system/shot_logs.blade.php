@extends('layouts.user')

@section('content')
<div class="shot-log-page">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">正規連の変更履歴</h4>
            <p class="text-muted mb-1">正規連の入力・変更・取消を、新しい順に表示します。日時は日本時間です。</p>
            <p class="text-muted small mb-0">記録者は操作時のログインアカウントです。保存に成功して値が変わった操作が対象で、機能追加前の変更履歴はありません。</p>
            <p class="text-muted small mb-0">履歴は自動削除されません。1か月より古い履歴は、この画面の削除ボタンで整理できます。</p>
        </div>
        <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary">システム管理</a>
    </div>

    @if(session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.system.shot-logs') }}" class="shot-log-filters mb-3">
        <div>
            <label for="log-practice-date" class="form-label">練習日（記録の対象日）</label>
            <input id="log-practice-date" type="date" name="practice_date" value="{{ $filters['practice_date'] ?? '' }}" class="form-control">
        </div>
        <div>
            <label for="log-changed-date" class="form-label">操作日（実際に変更した日）</label>
            <input id="log-changed-date" type="date" name="changed_date" value="{{ $filters['changed_date'] ?? '' }}" class="form-control">
        </div>
        <div>
            <label for="log-group" class="form-label">グループ</label>
            <input id="log-group" type="search" name="group" value="{{ $filters['group'] ?? '' }}" class="form-control" placeholder="グループ名・ID" maxlength="255">
        </div>
        <div>
            <label for="log-actor" class="form-label">記録者（操作した人）</label>
            <input id="log-actor" type="search" name="actor" value="{{ $filters['actor'] ?? '' }}" class="form-control" placeholder="名前・ユーザー名・ID" maxlength="255">
        </div>
        <div>
            <label for="log-subject" class="form-label">対象者（射った人）</label>
            <input id="log-subject" type="search" name="subject" value="{{ $filters['subject'] ?? '' }}" class="form-control" placeholder="名前・ユーザー名・ID" maxlength="255">
        </div>
        <div>
            <label for="log-tate" class="form-label">何立目（その日の通し番号）</label>
            <input id="log-tate" type="number" name="tate_no" value="{{ $filters['tate_no'] ?? '' }}" class="form-control" min="1" step="1" placeholder="すべて">
        </div>
        <div>
            <label for="log-shot" class="form-label">何射目</label>
            <select id="log-shot" name="shot_no" class="form-select">
                <option value="">すべて</option>
                @for($shotNo = 1; $shotNo <= 4; $shotNo++)
                    <option value="{{ $shotNo }}" @selected((string) ($filters['shot_no'] ?? '') === (string) $shotNo)>{{ $shotNo }}射目</option>
                @endfor
            </select>
        </div>
        <div>
            <label for="log-shot-id" class="form-label">射ID（同じ射の履歴を追う）</label>
            <input id="log-shot-id" type="number" name="shot_id" value="{{ $filters['shot_id'] ?? '' }}" class="form-control" min="1" step="1" placeholder="すべて">
        </div>
        <div class="shot-log-filter-actions d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-dark">絞り込む</button>
            <a href="{{ route('admin.system.shot-logs') }}" class="btn btn-outline-secondary">条件をクリア</a>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.system.shot-logs.destroy-expired') }}"
          class="border rounded p-3 mb-3 d-flex justify-content-between align-items-center gap-3 flex-wrap"
          onsubmit="return confirm('表示されている期限より前の変更履歴を削除します。絞り込みに関係なく全グループが対象です。よろしいですか？');">
        @csrf
        @method('DELETE')
        <input type="hidden" name="cutoff" value="{{ $deletionCutoff->format('Y-m-d H:i:s.u') }}">
        <div>
            <strong>古い履歴の削除</strong>
            <div class="text-muted small">操作日時が {{ $deletionCutoff->format('Y/m/d H:i:s') }} より前の変更履歴が対象です。</div>
            <div class="text-muted small">絞り込み条件に関係なく、全グループが対象です。的中記録そのものは削除されません。</div>
        </div>
        <button type="submit" class="btn btn-outline-danger" @disabled($expiredLogCount === 0)>
            1か月より古い履歴を削除（{{ number_format($expiredLogCount) }}件）
        </button>
    </form>

    <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
        <strong>{{ number_format($logs->total()) }}件</strong>
        <span class="text-muted small">名前・グループ・立番号は変更時点の情報です。</span>
    </div>

    <div class="table-responsive border rounded">
        <table class="table table-striped table-hover align-middle mb-0 shot-log-table">
            <thead>
                <tr>
                    <th scope="col">操作日時</th>
                    <th scope="col">グループ／練習日</th>
                    <th scope="col">記録者</th>
                    <th scope="col">対象者</th>
                    <th scope="col">立・射</th>
                    <th scope="col">変更内容</th>
                    <th scope="col">履歴</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>
                            <time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('Y/m/d') }}<br>{{ $log->created_at->format('H:i:s.v') }}</time>
                            <div class="small text-muted">{{ $log->source === 'home' ? 'ホーム' : 'グループ記録' }}</div>
                        </td>
                        <td>
                            <div>{{ $log->group_name ?? 'グループ未特定' }}</div>
                            <strong>{{ $log->practice_date->format('Y/m/d') }}</strong>
                        </td>
                        <td>
                            <div>{{ $log->actor_name }}</div>
                            <div class="small text-muted">{{ $log->actor_username }} / ID {{ $log->actor_user_id }}</div>
                        </td>
                        <td>
                            <div>{{ $log->subject_name }}</div>
                            <div class="small text-muted">{{ $log->subject_username }} / ID {{ $log->subject_user_id }}</div>
                        </td>
                        <td>
                            <strong>{{ $log->tate_no }}立目・{{ $log->shot_no }}射目</strong>
                            <div class="small text-muted">{{ $log->official_sheet_no }}ページ目</div>
                        </td>
                        <td>
                            <div class="small text-muted">{{ $log->operationLabel() }}</div>
                            <div class="shot-log-values"><span>{{ $log->oldValueLabel() }}</span> <span class="text-muted" aria-label="から">→</span> <strong>{{ $log->newValueLabel() }}</strong></div>
                        </td>
                        <td>
                            <a href="{{ route('admin.system.shot-logs', ['shot_id' => $log->shot_id]) }}">この射の履歴</a>
                            <div class="small text-muted">射ID {{ $log->shot_id }}</div>
                            <div class="small text-muted">ログ #{{ $log->id }}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">条件に一致する変更履歴はありません。</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>
</div>

<style>
.shot-log-page { padding-bottom: 24px; }
.shot-log-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    padding: 16px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}
.shot-log-filters .form-label { font-size: 14px; }
.shot-log-filter-actions { grid-column: 1 / -1; }
.shot-log-table { min-width: 1000px; }
.shot-log-table th { white-space: nowrap; }
.shot-log-table td { overflow-wrap: anywhere; }
.shot-log-values { white-space: nowrap; font-size: 18px; }
@media (max-width: 1000px) {
    .shot-log-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 600px) {
    .shot-log-filters { grid-template-columns: minmax(0, 1fr); }
}
</style>
@endsection
