@php
    use App\Models\Item;

    $avatarScale = (float) ($scale ?? 1);
    $avatarWidth = (int) round(Item::CANVAS_WIDTH * $avatarScale);
    $avatarHeight = (int) round(Item::CANVAS_HEIGHT * $avatarScale);
    $avatarLayers = $avatar ? $avatar->displayItems() : collect();
    $avatarClass = $class ?? '';
    $forceCanvas = (bool) ($forceCanvas ?? false);
@endphp

@once
    <style>
        .avatar-stack {
            position: relative;
            display: inline-block;
            overflow: hidden;
            flex: 0 0 auto;
            vertical-align: middle;
        }

        .avatar-stack-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: {{ Item::CANVAS_WIDTH }}px;
            height: {{ Item::CANVAS_HEIGHT }}px;
            transform-origin: top left;
        }

        .avatar-stack-layer {
            position: absolute;
            object-fit: contain;
        }

        .avatar-stack-default {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
    </style>
@endonce

<div class="avatar-stack {{ $avatarClass }}"
     style="width: {{ $avatarWidth }}px; height: {{ $avatarHeight }}px;"
     aria-label="アバター">
    @if($avatarLayers->isNotEmpty() || $forceCanvas)
        <div class="avatar-stack-canvas" style="transform: scale({{ $avatarScale }});">
            @foreach($avatarLayers as $avatarPart => $avatarItem)
                <img src="{{ asset($avatarItem->assetPath()) }}"
                     class="avatar-stack-layer"
                     data-preview-part="{{ $avatarPart }}"
                     alt=""
                     style="left: {{ $avatarItem->position_x }}px; top: {{ $avatarItem->position_y }}px; width: {{ $avatarItem->display_width }}px; height: {{ $avatarItem->display_height }}px; z-index: {{ $avatarItem->z_index }};">
            @endforeach
        </div>
    @else
        <img src="{{ asset('avatars/default.png') }}" class="avatar-stack-default" alt="">
    @endif
</div>
