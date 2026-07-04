<x-app-layout>
<x-slot name="header">
    {{ $user->name }} のステータス編集
</x-slot>

<form method="POST"
      action="{{ route('admin.users.status.update', $user) }}"
      class="card p-4 bg-white">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label class="form-label">レベル</label>
        <input type="number" name="level"
               class="form-control"
               value="{{ old('level', $user->level) }}">
    </div>

    <div class="mb-3">
        <label class="form-label">経験値</label>
        <input type="number" name="exp"
               class="form-control"
               value="{{ old('exp', $user->exp) }}">
    </div>

    <div class="mb-3">
        <label class="form-label">次レベル必要EXP</label>
        <input type="number" name="next_exp"
               class="form-control"
               value="{{ old('next_exp', $user->next_exp) }}">
    </div>

    <div class="mb-3">
        <label class="form-label">ポイント</label>
        <input type="number" name="point"
               class="form-control"
               value="{{ old('point', $user->point) }}">
    </div>

    <button class="btn btn-success">
        保存
    </button>
</form>
</x-app-layout>

