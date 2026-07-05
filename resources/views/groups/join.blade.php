@extends('layouts.user')

@section('content')
<div class="container">
    <div class="container mt-4">
    <h2>グループ参加</h2>

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="/groups/join">
        @csrf

        <div class="mb-3">
            <label>招待コード</label>
            <input type="text"
                   name="invite_code"
                   class="form-control @error('invite_code') is-invalid @enderror"
                   value="{{ old('invite_code') }}"
                   inputmode="numeric"
                   pattern="\d{4}"
                   maxlength="4"
                   autocomplete="one-time-code"
                   placeholder="例：1234">
            @error('invite_code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        
        <div>
            <a href="/groups" class="btn btn-secondary">
                戻る
            </a>
            <button class="btn btn-success">参加</button>
        </div>

    </form>
</div>
@endsection
