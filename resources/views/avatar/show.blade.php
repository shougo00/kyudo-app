@extends('layouts.user')

@section('content')
<div class="container py-5 text-center">
    <h2 class="mb-4">あなたのアバター</h2>

    <div class="d-flex justify-content-center">
        <div class="avatar-show-frame">
            @include('avatar.partials.stack', ['avatar' => $avatar, 'scale' => 0.82])
        </div>
    </div>

    <a href="{{ route('avatar.edit') }}" class="btn btn-secondary mt-4">
        {{ $avatar ? '編集する' : 'アバターを作成する' }}
    </a>
</div>

<style>
.avatar-show-frame {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 18px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #fff;
}
</style>
@endsection
