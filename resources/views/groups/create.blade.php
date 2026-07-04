@extends('layouts.user')

@section('content')
<div class="container mt-4">
    <h2>グループ作成</h2>

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="/groups">
        @csrf

        <div class="mb-3">
            <label>
                グループ名（学校名） ※一度グループを作成するとホストユーザになります。メイン記録端末で作成してください。
            </label>
            <input type="text" name="name" class="form-control">
        </div>


        {{-- 固定ボタン --}}
        <div>
            <a href="/groups" class="btn btn-secondary">
                戻る
            </a>

            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('本当に作成しますか？')">>
                作成
            </button>
        </div>

    </form>
</div>
@endsection