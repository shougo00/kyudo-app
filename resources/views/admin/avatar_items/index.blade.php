@extends('layouts.user')

@section('content')
<div class="avatar-admin-page">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div>
            <h4 class="mb-1">アバター素材管理</h4>
            <div class="text-muted">顔・胴体・ズボン・靴・アイテムの画像と位置、大きさを管理します。</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.system.avatar-items.export') }}" target="_blank" class="btn btn-outline-dark">A4シート出力</a>
            <a href="{{ route('admin.system.index') }}" class="btn btn-outline-secondary">システム管理へ戻る</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            入力内容を確認してください。
        </div>
    @endif

    <div class="avatar-admin-panel mb-3">
        <form method="POST" action="{{ route('admin.system.avatar-items.store') }}" enctype="multipart/form-data" class="avatar-upload-form">
            @csrf
            <div>
                <label class="form-label">カテゴリ</label>
                <select name="type" class="form-select" required>
                    @foreach($typeLabels as $type => $label)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">素材名</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div>
                <label class="form-label">画像</label>
                <input type="file" name="image" class="form-control" accept=".png,.jpg,.jpeg,.webp,.gif,.svg,image/*" required>
            </div>
            <div class="avatar-upload-action">
                <button type="submit" class="btn btn-primary">画像をインポート</button>
            </div>
        </form>
    </div>

    <div class="avatar-admin-tabs mb-3">
        <a href="{{ route('admin.system.avatar-items') }}" class="{{ $selectedType === 'all' ? 'active' : '' }}">全て</a>
        @foreach($typeLabels as $type => $label)
            <a href="{{ route('admin.system.avatar-items', ['type' => $type]) }}" class="{{ $selectedType === $type ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="avatar-admin-grid">
        @forelse($items as $item)
            <form method="POST" action="{{ route('admin.system.avatar-items.update', $item) }}" class="avatar-item-card" data-avatar-item-card>
                @csrf
                @method('PATCH')

                <div class="avatar-item-head">
                    <div>
                        <strong>{{ $item->name }}</strong>
                        <span>{{ $typeLabels[$item->type] ?? $item->type }} / {{ $item->image_path }}</span>
                    </div>
                    <span class="badge {{ $item->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $item->is_active ? '使用中' : '非表示' }}
                    </span>
                </div>

                <div class="avatar-item-body">
                    <div class="avatar-item-preview">
                        <div class="avatar-item-preview-canvas">
                            <img src="{{ asset($item->assetPath()) }}"
                                 alt="{{ $item->name }}"
                                 data-preview-image
                                 style="left: {{ $item->position_x }}px; top: {{ $item->position_y }}px; width: {{ $item->display_width }}px; height: {{ $item->display_height }}px; z-index: {{ $item->z_index }};">
                        </div>
                    </div>

                    <div class="avatar-item-controls">
                        <div class="avatar-field-row">
                            <label>
                                素材名
                                <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $item->name) }}" required>
                            </label>
                            <label>
                                カテゴリ
                                <select name="type" class="form-select form-select-sm">
                                    @foreach($typeLabels as $type => $label)
                                        <option value="{{ $type }}" @selected(old('type', $item->type) === $type)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="avatar-number-grid">
                            <label>
                                X
                                <input type="number" name="position_x" class="form-control form-control-sm" value="{{ old('position_x', $item->position_x) }}" step="1">
                            </label>
                            <label>
                                Y
                                <input type="number" name="position_y" class="form-control form-control-sm" value="{{ old('position_y', $item->position_y) }}" step="1">
                            </label>
                            <label>
                                幅（大きさ）
                                <input type="number" name="display_width" class="form-control form-control-sm" value="{{ old('display_width', $item->display_width) }}" min="1" step="1">
                            </label>
                            <label>
                                高さ（大きさ）
                                <input type="number" name="display_height" class="form-control form-control-sm" value="{{ old('display_height', $item->display_height) }}" min="1" step="1">
                            </label>
                            <label>
                                重なり
                                <input type="number" name="z_index" class="form-control form-control-sm" value="{{ old('z_index', $item->z_index) }}" min="0" step="1">
                            </label>
                        </div>

                        <div class="avatar-item-actions">
                            <label class="form-check mb-0">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                                <span class="form-check-label">ユーザーに表示</span>
                            </label>
                            <button type="submit" class="btn btn-sm btn-dark">保存</button>
                        </div>
                    </div>
                </div>
            </form>
        @empty
            <div class="avatar-admin-panel text-muted">素材がありません。</div>
        @endforelse
    </div>

    <div class="mt-3">
        {{ $items->links() }}
    </div>
</div>

<style>
.avatar-admin-page {
    padding-bottom: 24px;
}

.avatar-admin-panel,
.avatar-item-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
}

.avatar-admin-panel {
    padding: 14px;
}

.avatar-upload-form {
    display: grid;
    grid-template-columns: 160px minmax(180px, 1fr) minmax(220px, 1.4fr) auto;
    gap: 12px;
    align-items: end;
}

.avatar-upload-action {
    display: flex;
    justify-content: flex-end;
}

.avatar-admin-tabs {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 4px;
}

.avatar-admin-tabs a {
    flex: 0 0 auto;
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 7px 12px;
    color: #495057;
    background: #f8f9fa;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.avatar-admin-tabs a.active {
    color: #fff;
    border-color: #111;
    background: #111;
}

.avatar-admin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(430px, 1fr));
    gap: 12px;
}

.avatar-item-card {
    padding: 12px;
}

.avatar-item-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 10px;
}

.avatar-item-head strong,
.avatar-item-head span {
    display: block;
}

.avatar-item-head span {
    color: #6c757d;
    font-size: 12px;
    word-break: break-all;
}

.avatar-item-body {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 12px;
    align-items: start;
}

.avatar-item-preview {
    width: 180px;
    height: 270px;
    overflow: hidden;
    border: 1px solid #ced4da;
    border-radius: 8px;
    background:
        linear-gradient(#f8f9fa 1px, transparent 1px),
        linear-gradient(90deg, #f8f9fa 1px, transparent 1px),
        #fff;
    background-size: 15px 15px;
}

.avatar-item-preview-canvas {
    position: relative;
    width: 300px;
    height: 450px;
    transform: scale(0.6);
    transform-origin: top left;
}

.avatar-item-preview-canvas img {
    position: absolute;
    object-fit: contain;
}

.avatar-item-controls,
.avatar-field-row {
    display: grid;
    gap: 10px;
}

.avatar-field-row {
    grid-template-columns: minmax(0, 1fr) 130px;
}

.avatar-number-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(58px, 1fr));
    gap: 8px;
}

.avatar-item-controls label {
    color: #495057;
    font-size: 12px;
    font-weight: 700;
}

.avatar-item-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

@media (max-width: 700px) {
    .avatar-upload-form,
    .avatar-admin-grid,
    .avatar-item-body {
        grid-template-columns: 1fr;
    }

    .avatar-item-preview {
        margin: 0 auto;
    }

    .avatar-field-row {
        grid-template-columns: 1fr;
    }

    .avatar-number-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .avatar-item-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .avatar-item-actions .btn,
    .avatar-upload-action .btn {
        width: 100%;
    }
}
</style>

<script>
document.querySelectorAll('[data-avatar-item-card]').forEach(card => {
    const image = card.querySelector('[data-preview-image]');

    if (!image) {
        return;
    }

    const syncPreview = () => {
        const x = card.querySelector('[name="position_x"]').value || 0;
        const y = card.querySelector('[name="position_y"]').value || 0;
        const width = card.querySelector('[name="display_width"]').value || 1;
        const height = card.querySelector('[name="display_height"]').value || 1;
        const zIndex = card.querySelector('[name="z_index"]').value || 0;

        image.style.left = `${x}px`;
        image.style.top = `${y}px`;
        image.style.width = `${width}px`;
        image.style.height = `${height}px`;
        image.style.zIndex = zIndex;
    };

    card
        .querySelectorAll('[name="position_x"], [name="position_y"], [name="display_width"], [name="display_height"], [name="z_index"]')
        .forEach(input => input.addEventListener('input', syncPreview));
});
</script>
@endsection
