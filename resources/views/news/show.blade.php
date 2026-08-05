@extends('layouts.user')

@section('content')
<div class="news-show-page py-2">
    <div class="mb-3">
        <a href="{{ route('news.index') }}" class="btn btn-outline-secondary">一覧へ戻る</a>
    </div>

    <article class="news-detail">
        <div class="news-date">{{ $news->created_at->format('Y/m/d') }}</div>
        <h4>{{ $news->title }}</h4>

        @if($news->imageUrl())
            <img src="{{ $news->imageUrl() }}" class="news-detail-image" alt="">
        @endif

        <div class="news-body">
            {!! nl2br(e($news->body)) !!}
        </div>
    </article>
</div>

<style>
.news-detail {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
    padding: 18px;
}

.news-date {
    color: #6c757d;
    font-size: 0.9rem;
}

.news-detail h4 {
    margin: 4px 0 16px;
    overflow-wrap: anywhere;
}

.news-detail-image {
    width: 100%;
    max-height: 420px;
    object-fit: contain;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
    margin-bottom: 18px;
}

.news-body {
    color: #212529;
    line-height: 1.8;
    overflow-wrap: anywhere;
    white-space: normal;
}
</style>
@endsection
