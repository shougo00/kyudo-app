<x-app-layout>
<div class="container py-4">

    <h1 class="mb-4">お知らせ管理</h1>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('news.create') }}" class="btn btn-primary mb-3">
        新規作成
    </a>

    {{-- ========================= --}}
    {{-- PC / タブレット用 TABLE --}}
    {{-- ========================= --}}
    <div class="table-responsive d-none d-md-block">
        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>タイトル</th>
                    <th>公開</th>
                    <th>作成日</th>
                    <th style="width:180px">操作</th>
                </tr>
            </thead>
            <tbody>
            @foreach($news as $item)
                <tr>
                    <td>{{ $item->title }}</td>
                    <td>
                        @if($item->is_published)
                            <span class="badge bg-success">公開</span>
                        @else
                            <span class="badge bg-secondary">非公開</span>
                        @endif
                    </td>
                    <td>{{ $item->created_at->format('Y/m/d') }}</td>
                    <td class="text-center">
                        <a href="{{ route('news.edit', $item) }}"
                           class="btn btn-warning btn-sm mb-1 w-100">
                           編集
                        </a>

                        <form action="{{ route('news.destroy', $item) }}"
                              method="POST"
                              onsubmit="return confirm('削除しますか？')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm w-100">
                                削除
                            </button>
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

        @foreach($news as $item)
        <div class="card mb-3 shadow-sm">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start mb-2">
                    <strong class="me-2">{{ $item->title }}</strong>
                    @if($item->is_published)
                        <span class="badge bg-success">公開</span>
                    @else
                        <span class="badge bg-secondary">非公開</span>
                    @endif
                </div>

                <div class="small text-muted mb-3">
                    作成日：{{ $item->created_at->format('Y/m/d') }}
                </div>

                <a href="{{ route('news.edit', $item) }}"
                   class="btn btn-warning btn-sm w-100 mb-2">
                    編集
                </a>

                <form action="{{ route('news.destroy', $item) }}"
                      method="POST"
                      onsubmit="return confirm('削除しますか？')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm w-100">
                        削除
                    </button>
                </form>

            </div>
        </div>
        @endforeach

    </div>

    {{ $news->links() }}

</div>
</x-app-layout>
