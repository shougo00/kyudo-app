@extends('layouts.user')

@section('content')
<div class="news-admin-page py-2">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">お知らせ管理</h4>
            <div class="text-muted">配信先を選んで、ユーザーのお知らせ画面に届けます。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary">システム管理</a>
            <a href="{{ route('admin.news.create') }}" class="btn btn-primary">新規作成</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="table-responsive d-none d-md-block">
        <table class="table table-bordered align-middle bg-white">
            <thead class="table-dark">
                <tr>
                    <th>タイトル</th>
                    <th>画像</th>
                    <th>配信先</th>
                    <th>公開</th>
                    <th>作成日</th>
                    <th style="width:180px">操作</th>
                </tr>
            </thead>
            <tbody>
            @forelse($news as $item)
                <tr>
                    <td class="fw-semibold">{{ $item->title }}</td>
                    <td>
                        @if($item->imageUrl())
                            <img src="{{ $item->imageUrl() }}" class="news-thumb" alt="">
                        @else
                            <span class="text-muted small">なし</span>
                        @endif
                    </td>
                    <td>{{ $item->recipients_count }}人</td>
                    <td>
                        @if($item->is_published)
                            <span class="badge text-bg-success">公開</span>
                        @else
                            <span class="badge text-bg-secondary">非公開</span>
                        @endif
                    </td>
                    <td>{{ $item->created_at->format('Y/m/d') }}</td>
                    <td class="text-center">
                        <a href="{{ route('admin.news.edit', $item) }}" class="btn btn-warning btn-sm mb-1 w-100">編集</a>

                        <form action="{{ route('admin.news.destroy', $item) }}"
                              method="POST"
                              onsubmit="return confirm('削除しますか？')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm w-100">削除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">お知らせはまだありません。</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-md-none">
        @forelse($news as $item)
            <div class="news-admin-card">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <strong>{{ $item->title }}</strong>
                        <div class="small text-muted">{{ $item->created_at->format('Y/m/d') }} / 配信先 {{ $item->recipients_count }}人</div>
                    </div>
                    <span class="badge {{ $item->is_published ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $item->is_published ? '公開' : '非公開' }}
                    </span>
                </div>

                @if($item->imageUrl())
                    <img src="{{ $item->imageUrl() }}" class="news-card-image mb-2" alt="">
                @endif

                <a href="{{ route('admin.news.edit', $item) }}" class="btn btn-warning btn-sm w-100 mb-2">編集</a>

                <form action="{{ route('admin.news.destroy', $item) }}"
                      method="POST"
                      onsubmit="return confirm('削除しますか？')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm w-100">削除</button>
                </form>
            </div>
        @empty
            <div class="alert alert-light border">お知らせはまだありません。</div>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $news->links() }}
    </div>
</div>

<style>
.news-thumb {
    width: 64px;
    height: 44px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.news-admin-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
    padding: 12px;
    margin-bottom: 12px;
}

.news-card-image {
    width: 100%;
    max-height: 180px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}
</style>
@endsection
