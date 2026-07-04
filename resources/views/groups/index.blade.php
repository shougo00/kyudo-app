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

                            @if($avatar)

                                <div class="navbar-avatar-box">
                                    @foreach(['bottom','shoes','top','face','hair','accessory'] as $part)

                                        @if($avatar->$part)
                                            <img src="{{ asset('avatars/'.$part.'/'.$avatar->$part->image_path) }}"
                                                 class="navbar-avatar-layer {{ $part }}">
                                        @endif

                                    @endforeach
                                </div>

                            @else

                                <img src="{{ asset('avatars/default.png') }}"
                                     style="width:40px;height:50px;object-fit:contain;">

                            @endif

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