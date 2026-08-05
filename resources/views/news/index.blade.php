@extends('layouts.user')

@section('content')
<div class="news-page py-2">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">お知らせ</h4>
            <div class="text-muted">あなた宛てのお知らせ一覧</div>
        </div>

        @if($unreadCount > 0)
            <form method="POST" action="{{ route('news.mark-all-read') }}">
                @csrf
                <button class="btn btn-outline-secondary">すべて既読</button>
            </form>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="news-list">
        @forelse($news as $item)
            <a href="{{ route('news.show', $item) }}" class="news-list-item">
                @if($item->imageUrl())
                    <img src="{{ $item->imageUrl() }}" class="news-list-image" alt="">
                @endif

                <div class="news-list-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="news-date">{{ $item->created_at->format('Y/m/d') }}</div>
                            <h5>{{ $item->title }}</h5>
                        </div>
                        @if($item->pivot->read_at === null)
                            <span class="badge rounded-pill text-bg-danger">未読</span>
                        @else
                            <span class="badge rounded-pill text-bg-light text-secondary border">既読</span>
                        @endif
                    </div>
                    <p>{{ \Illuminate\Support\Str::limit($item->body, 110) }}</p>
                </div>
            </a>
        @empty
            <div class="empty-news">
                <strong>お知らせはありません。</strong>
                <span>新しいお知らせが届くとここに表示されます。</span>
            </div>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $news->links() }}
    </div>
</div>

<style>
.news-list {
    display: grid;
    gap: 12px;
}

.news-list-item {
    display: grid;
    grid-template-columns: 160px minmax(0, 1fr);
    gap: 14px;
    padding: 14px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
    color: inherit;
    text-decoration: none;
}

.news-list-item:hover {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.12);
}

.news-list-image {
    width: 100%;
    height: 106px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.news-list-body {
    min-width: 0;
}

.news-date {
    color: #6c757d;
    font-size: 0.85rem;
}

.news-list h5 {
    margin: 2px 0 8px;
    overflow-wrap: anywhere;
}

.news-list p {
    margin: 0;
    color: #495057;
    overflow-wrap: anywhere;
}

.empty-news {
    display: grid;
    gap: 4px;
    padding: 24px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
    color: #6c757d;
}

.empty-news strong {
    color: #212529;
}

@media (max-width: 575px) {
    .news-list-item {
        grid-template-columns: 1fr;
    }

    .news-list-image {
        height: 180px;
    }
}
</style>
@endsection
