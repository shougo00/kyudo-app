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

                            @if($avatar)

                                <div class="group-member-avatar-box">
                                    @foreach(['bottom','shoes','top','face','hair','accessory'] as $part)

                                        @if($avatar->$part)
                                            <img src="{{ asset('avatars/'.$part.'/'.$avatar->$part->image_path) }}"
                                                 class="group-member-avatar-layer {{ $part }}">
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

<style>
.group-member-avatar-box {
    position: relative;
    width: 52px;
    height: 62px;
    margin: 0 auto;
    flex-shrink: 0;
}

.group-member-avatar-layer {
    position: absolute;
    object-fit: contain;
}

.group-member-avatar-layer.hair {
    top: 0;
    left: 0;
    width: 52px;
    height: 24px;
    z-index: 6;
}

.group-member-avatar-layer.face {
    top: 13px;
    left: 13px;
    width: 26px;
    height: 26px;
    z-index: 5;
}

.group-member-avatar-layer.top {
    top: 32px;
    left: 0;
    width: 52px;
    height: 18px;
    z-index: 4;
}

.group-member-avatar-layer.bottom {
    top: 45px;
    left: 0;
    width: 52px;
    height: 12px;
    z-index: 3;
}

.group-member-avatar-layer.shoes {
    top: 56px;
    left: 8px;
    width: 36px;
    height: 6px;
    z-index: 2;
}

.group-member-avatar-layer.accessory {
    top: 0;
    left: 8px;
    width: 36px;
    height: 12px;
    z-index: 7;
}
</style>
@endsection
