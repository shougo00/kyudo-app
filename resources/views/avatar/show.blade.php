@extends('layouts.user')

@section('content')
<div class="container py-5 text-center">
    <h2 class="mb-4">あなたのアバター</h2>

    @if(!$avatar)
        <a href="{{ route('avatar.edit') }}" class="btn btn-primary btn-lg">アバターを作成する</a>
    @else
        <div class="d-flex justify-content-center">
            <div class="avatar-box">
                @foreach(['bottom','shoes','top','face','hair','accessory'] as $part)
                    @if($avatar->$part)
                        <img src="{{ asset('avatars/'.$part.'/'.$avatar->$part->image_path) }}" 
                             class="avatar-layer {{ $part }}">
                    @endif
                @endforeach
            </div>
        </div>

        <a href="{{ route('avatar.edit') }}" class="btn btn-secondary mt-4">編集する</a>
    @endif
</div>

<style>
.avatar-box {
    position: relative;
    width: 300px;   /* キャンバス幅 */
    height: 450px;  /* キャンバス高さ */
    margin: auto;
}

/* 全パーツ共通 */
.avatar-layer {
    position: absolute;
}

/* パーツごとに位置とサイズを自由に変更 */
.hair {
    top: -30px;     /* 上からの距離 */
    left: 50px;    /* 左からの距離 */
    width: 200px; /* 幅 */
    height: 150px;/* 高さ */
}

.face {
    top: 50px;
    left: 75px;
    width: 150px;
    height: 150px;
}

.top {
    top: 160px;
    left: 0px;
    width: 300px;
    height: 300px;
}

.bottom {
    top: 450px;
    left: -50px;
    width:400px;
    height: 300px;
}

.shoes {
    top: 400px;
    left: 50px;
    width: 200px;
    height: 50px;
}

.accessory {
    top: 0px;
    left: 50px;
    width: 200px;
    height: 50px;
}
</style>
@endsection
