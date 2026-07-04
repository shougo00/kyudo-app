@extends('layouts.user')

@section('content')
<div class="container py-5">
    <h2 class="mb-4 text-center">アバター編集</h2>

    <form method="POST" action="{{ route('avatar.update') }}">
        @csrf
        <div class="card mx-auto" style="max-width: 700px;">
            <div class="card-body">

                @php
                    $parts = [
                        'hair' => ['label'=>'帽子','items'=>$hairs],
                        'face' => ['label'=>'顔','items'=>$faces],
                        'top' => ['label'=>'上着','items'=>$tops],
                        'accessory' => ['label'=>'アクセサリー','items'=>$accessories],
                    ];
                @endphp

                @foreach($parts as $part => $data)
                
                    <div class="mb-4">
                        <label class="form-label">{{ $data['label'] }}</label>
                        <div class="d-flex flex-wrap gap-2">

                            {{-- なし選択 --}}
                            <label class="avatar-option">
                                <input type="radio" name="{{ $part }}_id" value="" 
                                    {{ optional($avatar)->{$part.'_id'} === null ? 'checked' : '' }} hidden>
                                <div class="option-img d-flex align-items-center justify-content-center" 
                                     style="width:60px; height:60px; border:2px dashed #ccc; border-radius:5px;">
                                    なし
                                </div>
                            </label>

                            {{-- 元のアイテム --}}
                            @foreach($data['items'] as $item)
                                <label class="avatar-option">
                                    <input type="radio" name="{{ $part }}_id" value="{{ $item->id }}"
                                        {{ optional($avatar)->{$part.'_id'} === $item->id ? 'checked' : '' }} hidden>
                                    <img src="{{ asset('avatars/'.$part.'/'.$item->image_path) }}"
                                         class="img-thumbnail option-img {{ optional($avatar)->{$part.'_id'} === $item->id ? 'selected' : '' }}">
                                </label>
                            @endforeach

                        </div>
                    </div>
                @endforeach

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">保存する</button>
                    <button type="button" id="reset-avatar" class="btn btn-secondary btn-lg ms-2">全てリセット</button>
                </div>

            </div>
        </div>
    </form>
</div>

<style>
.avatar-option {
    cursor: pointer;
    display: inline-block;
    position: relative;
}

.option-img {
    width: 60px;
    height: 60px;
    object-fit: contain;
    border: 2px solid transparent;
    border-radius: 5px;
    transition: 0.2s;
}

.option-img:hover {
    border-color: #007bff;
}

.option-img.selected {
    border-color: #007bff;
    box-shadow: 0 0 5px #007bff;
    text-align: center;
    font-size: 0.8rem;
}
</style>

<script>
document.querySelectorAll('.avatar-option input').forEach(input => {
    input.addEventListener('change', () => {
        const name = input.name;
        document.querySelectorAll(`input[name="${name}"] + img, input[name="${name}"] + div`).forEach(el => el.classList.remove('selected'));
        input.nextElementSibling.classList.add('selected');
    });
});

// 全てリセットボタン
document.getElementById('reset-avatar').addEventListener('click', () => {
    @foreach($parts as $part => $data)
        let inputs_{{ $part }} = document.querySelectorAll('input[name="{{ $part }}_id"]');
        inputs_{{ $part }}[0].checked = true; // なしをチェック
        inputs_{{ $part }}[0].nextElementSibling.classList.add('selected');
        inputs_{{ $part }}[0].nextElementSibling.parentElement.querySelectorAll('img, div').forEach(el => {
            if(el !== inputs_{{ $part }}[0].nextElementSibling) el.classList.remove('selected');
        });
    @endforeach
});
</script>
@endsection
