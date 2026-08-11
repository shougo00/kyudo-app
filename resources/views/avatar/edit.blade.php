@extends('layouts.user')

@section('content')
<div class="container py-4 avatar-edit-page">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
        <div>
            <h2 class="mb-1">アバター編集</h2>
            <div class="text-muted">顔・胴体・ズボン・靴・アイテムを重ねてアバターを作ります。</div>
        </div>
        <a href="{{ route('avatar.show') }}" class="btn btn-outline-secondary">戻る</a>
    </div>

    <form method="POST" action="{{ route('avatar.update') }}">
        @csrf

        <div class="avatar-edit-layout">
            <div class="avatar-preview-panel">
                <div class="avatar-preview-title">プレビュー</div>
                @include('avatar.partials.stack', ['avatar' => $avatar, 'scale' => 0.62, 'class' => 'avatar-edit-preview', 'forceCanvas' => true])
            </div>

            <div class="avatar-options-panel">
                @foreach($parts as $part => $label)
                    @php
                        $items = $itemsByType->get($part, collect());
                        $selectedId = optional($avatar)->{$part . '_id'};
                        if (!$items->contains('id', $selectedId)) {
                            $selectedId = null;
                        }
                    @endphp

                    <section class="avatar-part-section">
                        <div class="avatar-part-head">
                            <label class="form-label mb-0">{{ $label }}</label>
                            <span class="text-muted small">{{ $items->count() }}個</span>
                        </div>

                        <div class="avatar-option-list">
                            <label class="avatar-option">
                                <input type="radio"
                                       name="{{ $part }}_id"
                                       value=""
                                       data-part="{{ $part }}"
                                       {{ $selectedId === null ? 'checked' : '' }}
                                       hidden>
                                <span class="option-img option-none {{ $selectedId === null ? 'selected' : '' }}">なし</span>
                            </label>

                            @foreach($items as $item)
                                <label class="avatar-option">
                                    <input type="radio"
                                           name="{{ $part }}_id"
                                           value="{{ $item->id }}"
                                           data-part="{{ $part }}"
                                           data-src="{{ asset($item->assetPath()) }}"
                                           data-x="{{ $item->position_x }}"
                                           data-y="{{ $item->position_y }}"
                                           data-width="{{ $item->display_width }}"
                                           data-height="{{ $item->display_height }}"
                                           data-z="{{ $item->z_index }}"
                                           {{ (int) $selectedId === (int) $item->id ? 'checked' : '' }}
                                           hidden>
                                    <img src="{{ asset($item->assetPath()) }}"
                                         alt="{{ $item->name }}"
                                         class="img-thumbnail option-img {{ (int) $selectedId === (int) $item->id ? 'selected' : '' }}">
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div class="avatar-edit-actions">
                    <button type="submit" class="btn btn-primary btn-lg">保存する</button>
                    <button type="button" id="reset-avatar" class="btn btn-secondary btn-lg">全てリセット</button>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.avatar-edit-layout {
    display: grid;
    grid-template-columns: minmax(220px, 320px) 1fr;
    gap: 18px;
    align-items: flex-start;
}

.avatar-preview-panel,
.avatar-options-panel {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
}

.avatar-preview-panel {
    position: sticky;
    top: 12px;
    display: grid;
    justify-items: center;
    gap: 12px;
    padding: 16px;
}

.avatar-preview-title {
    width: 100%;
    font-weight: 800;
}

.avatar-options-panel {
    padding: 16px;
}

.avatar-part-section + .avatar-part-section {
    margin-top: 20px;
}

.avatar-part-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.avatar-option-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.avatar-option {
    cursor: pointer;
    display: inline-block;
}

.option-img {
    width: 68px;
    height: 68px;
    object-fit: contain;
    border: 2px solid transparent;
    border-radius: 6px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.option-none {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 2px dashed #ced4da;
    color: #6c757d;
    font-weight: 700;
    font-size: 13px;
    background: #f8f9fa;
}

.option-img:hover,
.option-img.selected {
    border-color: #0d6efd;
}

.option-img.selected {
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.16);
}

.avatar-edit-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 24px;
}

@media (max-width: 768px) {
    .avatar-edit-layout {
        grid-template-columns: 1fr;
    }

    .avatar-preview-panel {
        position: static;
    }

    .avatar-edit-actions .btn {
        flex: 1;
    }
}
</style>

<script>
document.querySelectorAll('.avatar-option input').forEach(input => {
    input.addEventListener('change', () => {
        const name = input.name;
        document
            .querySelectorAll(`input[name="${name}"] + img, input[name="${name}"] + span`)
            .forEach(el => el.classList.remove('selected'));

        input.nextElementSibling.classList.add('selected');
        updatePreview(input);
    });
});

document.getElementById('reset-avatar')?.addEventListener('click', () => {
    @foreach($parts as $part => $label)
        const inputs_{{ $part }} = document.querySelectorAll('input[name="{{ $part }}_id"]');
        if (inputs_{{ $part }}.length > 0) {
            inputs_{{ $part }}[0].checked = true;
            inputs_{{ $part }}[0].dispatchEvent(new Event('change', { bubbles: true }));
        }
    @endforeach
});

function updatePreview(input) {
    const preview = document.querySelector('.avatar-edit-preview .avatar-stack-canvas');
    if (!preview) {
        return;
    }

    const part = input.dataset.part;
    preview.querySelectorAll(`[data-preview-part="${part}"]`).forEach(layer => layer.remove());

    if (!input.dataset.src) {
        return;
    }

    const layer = document.createElement('img');
    layer.src = input.dataset.src;
    layer.alt = '';
    layer.className = 'avatar-stack-layer';
    layer.dataset.previewPart = part;
    layer.style.left = `${input.dataset.x}px`;
    layer.style.top = `${input.dataset.y}px`;
    layer.style.width = `${input.dataset.width}px`;
    layer.style.height = `${input.dataset.height}px`;
    layer.style.zIndex = input.dataset.z;
    preview.appendChild(layer);
}
</script>
@endsection
