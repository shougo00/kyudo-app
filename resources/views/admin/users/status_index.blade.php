<x-app-layout>
<div class="container py-4">

    <h1 class="mb-4">ユーザー ステータス管理</h1>

    {{-- ========================= --}}
    {{-- PC / タブレット用 TABLE --}}
    {{-- ========================= --}}
    <div class="table-responsive d-none d-md-block">
        <table class="table table-bordered bg-white align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>名前</th>
                    <th>Lv</th>
                    <th>EXP</th>
                    <th>次Lvまで</th>
                    <th>Pt</th>
                    <th style="width:260px">操作</th>
                </tr>
            </thead>
            <tbody>
            @foreach($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td><span class="badge bg-primary">Lv {{ $user->level }}</span></td>
                    <td>{{ $user->exp }}</td>
                    <td>{{ $user->next_exp }}</td>
                    <td>{{ $user->point }}</td>

                    <td class="text-center">

                        <a href="{{ route('admin.users.status.edit', $user) }}"
                           class="btn btn-sm btn-outline-primary w-100 mb-2">
                            編集
                        </a>

                        <form method="POST"
                              action="{{ route('admin.users.exp.add', $user) }}">
                            @csrf

                            <div class="input-group input-group-sm mb-2">
                                <input type="number" name="exp"
                                       class="form-control text-center"
                                       placeholder="EXP">
                                <button class="btn btn-primary px-2">＋</button>
                            </div>

                            <div class="d-flex gap-1">
                                <button name="exp" value="10"
                                        class="btn btn-outline-secondary btn-sm w-100">+10</button>
                                <button name="exp" value="50"
                                        class="btn btn-outline-secondary btn-sm w-100">+50</button>
                                <button name="exp" value="100"
                                        class="btn btn-outline-secondary btn-sm w-100">+100</button>
                            </div>
                        </form>

                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- ========================= --}}
    {{-- スマホ用 CARD 表示 --}}
    {{-- ========================= --}}
    <div class="d-md-none">

        @foreach($users as $user)
        <div class="card mb-3 shadow-sm">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>{{ $user->name }}</strong>
                    <span class="badge bg-primary">Lv {{ $user->level }}</span>
                </div>

                <div class="small text-muted mb-2">
                    ID: {{ $user->id }}
                </div>

                <div class="row text-center mb-3">
                    <div class="col">
                        <div class="fw-bold">{{ $user->exp }}</div>
                        <small class="text-muted">EXP</small>
                    </div>
                    <div class="col">
                        <div class="fw-bold">{{ $user->next_exp }}</div>
                        <small class="text-muted">次Lv</small>
                    </div>
                    <div class="col">
                        <div class="fw-bold">{{ $user->point }}</div>
                        <small class="text-muted">Pt</small>
                    </div>
                </div>

                <a href="{{ route('admin.users.status.edit', $user) }}"
                   class="btn btn-outline-primary btn-sm w-100 mb-2">
                    ステータス編集
                </a>

                <form method="POST"
                      action="{{ route('admin.users.exp.add', $user) }}">
                    @csrf

                    <div class="input-group input-group-sm mb-2">
                        <input type="number" name="exp"
                               class="form-control text-center"
                               placeholder="EXP">
                        <button class="btn btn-primary">付与</button>
                    </div>

                    <div class="d-flex gap-2">
                        <button name="exp" value="10"
                                class="btn btn-outline-secondary btn-sm w-100">+10</button>
                        <button name="exp" value="50"
                                class="btn btn-outline-secondary btn-sm w-100">+50</button>
                        <button name="exp" value="100"
                                class="btn btn-outline-secondary btn-sm w-100">+100</button>
                    </div>
                </form>

            </div>
        </div>
        @endforeach

    </div>

    {{ $users->links() }}

</div>
</x-app-layout>
