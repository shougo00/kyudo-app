@extends('layouts.user')

@section('content')
@php
    $selectedUserIds = collect(old('recipient_ids', []))->map(fn($id) => (int) $id)->all();
@endphp

<div class="news-admin-page py-2">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">お知らせ新規作成</h4>
            <div class="text-muted">タイトル、本文、画像、配信先を設定します。</div>
        </div>
        <a href="{{ route('admin.news.index') }}" class="btn btn-outline-secondary">一覧へ戻る</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">入力内容を確認してください。</div>
    @endif

    <form method="POST" action="{{ route('admin.news.store') }}" enctype="multipart/form-data" class="news-form">
        @csrf

        <div class="news-form-panel">
            <div class="mb-3">
                <label class="form-label" for="title">タイトル</label>
                <input type="text" id="title" name="title" value="{{ old('title') }}" class="form-control" required>
                @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="body">本文</label>
                <textarea id="body" name="body" rows="7" class="form-control" required>{{ old('body') }}</textarea>
                @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="image">画像</label>
                <input type="file" id="image" name="image" class="form-control" accept="image/*">
                @error('image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="is_published" id="is_published" value="1" checked>
                <label class="form-check-label" for="is_published">公開する</label>
            </div>
        </div>

        <div class="news-form-panel">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                <label class="form-label mb-0">配信先ユーザー</label>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="small text-muted"><span id="recipientCount">0</span>人選択中</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllRecipients">全員を選択</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearRecipients">解除</button>
                </div>
            </div>
            @error('recipient_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

            <div class="recipient-grid" id="recipientGrid">
                @forelse($users as $user)
                    <label class="recipient-option">
                        <input type="checkbox"
                               name="recipient_ids[]"
                               value="{{ $user->id }}"
                               {{ in_array($user->id, $selectedUserIds, true) ? 'checked' : '' }}>
                        <span>
                            <strong>{{ $user->name }}</strong>
                            <small>{{ $user->username }}{{ $user->is_admin ? ' / 管理' : '' }}</small>
                        </span>
                    </label>
                @empty
                    <div class="alert alert-light border mb-0">配信できるユーザーがいません。</div>
                @endforelse
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary">保存</button>
            <a href="{{ route('admin.news.index') }}" class="btn btn-secondary">戻る</a>
        </div>
    </form>
</div>

@include('admin.news.partials.form_styles')
@include('admin.news.partials.recipient_script')
@endsection
