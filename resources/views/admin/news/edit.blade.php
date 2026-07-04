<x-app-layout>
<div class="container py-4">

<h1>お知らせ編集</h1>

<form method="POST" action="{{ route('news.update', $news) }}">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label class="form-label">タイトル</label>
        <input type="text"
               name="title"
               value="{{ $news->title }}"
               class="form-control"
               required>
    </div>

    <div class="mb-3">
        <label class="form-label">本文</label>
        <textarea name="body" rows="6"
                  class="form-control"
                  required>{{ $news->body }}</textarea>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox"
               name="is_published"
               id="is_published"
               {{ $news->is_published ? 'checked' : '' }}>
        <label class="form-check-label">
            公開する
        </label>
    </div>

    <button class="btn btn-primary">更新</button>
    <a href="{{ route('news.index') }}" class="btn btn-secondary">
        戻る
    </a>

</form>

</div>
</x-app-layout>
