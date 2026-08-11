@extends('layouts.user')

@section('content')
<div class="container">
    <div class="container mt-4">

        <h2>マイグループ</h2>

        <a href="/groups/create" class="btn btn-primary">グループ作成</a>
        <a href="/groups/join" class="btn btn-success">グループ参加</a>

        <hr>

        @if($groups->isEmpty())
            <p>まだグループに参加していません</p>
        @endif

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @foreach($groups as $group)

            <div class="card mb-3 p-3">

                <strong>{{ $group->name }}</strong><br>

                @if(auth()->id() === $group->host_user_id)
                    招待コード：{{ $group->invite_code }}
                @endif

                <hr>

                <strong>メンバー</strong>

                <div style="display:flex; gap:15px; flex-wrap:wrap; margin-top:10px;">

                    @foreach($group->users as $user)

                        <div style="width:80px; text-align:center;">

                            @php $avatar = $user->avatar; @endphp

                            @include('avatar.partials.stack', ['avatar' => $avatar, 'scale' => 0.15])

                            <div style="
                                font-size:12px;
                                margin-top:5px;
                                height:32px;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                line-height:1.2;
                                word-break:break-word;
                                text-align:center;
                            ">

                                <span>
                                    {{ $user->name }}

                                    @if($user->id === $group->host_user_id)
                                        👑
                                    @endif
                                </span>

                            </div>

                            @if(auth()->id() === $group->host_user_id && $user->id !== $group->host_user_id)
                                <form method="POST"
                                      action="{{ route('groups.members.remove', [$group->id, $user->id]) }}"
                                      class="mt-1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-outline-danger btn-sm"
                                            style="font-size:11px;padding:2px 6px;"
                                            onclick="return confirm('{{ $user->name }}さんをグループから退会させますか？')">
                                        退会
                                    </button>
                                </form>
                            @endif

                        </div>

                    @endforeach

                </div>

            </div>

            @if(auth()->id() !== $group->host_user_id)

                <form method="POST"
                      action="{{ route('groups.leave', $group->id) }}"
                      style="margin-top:10px;">

                    @csrf

                    <button class="btn btn-danger btn-sm"
                            onclick="return confirm('本当にグループを抜けますか？')">

                        グループを抜ける

                    </button>

                </form>

            @endif

        @endforeach

    </div>
</div>

@endsection
