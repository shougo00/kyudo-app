<section class="space-y-6">


<!-- 削除ボタン -->
<button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmUserDeletion">
    アカウントを削除
</button>

<!-- 削除確認モーダル -->
<div class="modal fade @if($errors->userDeletion->isNotEmpty()) show @endif" id="confirmUserDeletion" tabindex="-1" aria-labelledby="confirmUserDeletionLabel" aria-hidden="true" @if($errors->userDeletion->isNotEmpty()) style="display:block;" @endif>
  <div class="modal-dialog">
    <form method="POST" action="{{ route('profile.destroy') }}">
      @csrf
      @method('delete')
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="confirmUserDeletionLabel">本当にアカウントを削除しますか？</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        </div>
        <div class="modal-body">
          <p>アカウントを削除すると、すべてのデータやリソースが永久に削除されます。パスワードを入力して確認してください。</p>

          <div class="mb-3">
            <label for="password" class="form-label">パスワード</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="パスワード">
            @error('password')
              <div class="text-danger mt-1">{{ $message }}</div>
            @enderror
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-danger">アカウントを削除</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- 自動でモーダルを開くスクリプト（パスワードエラー時） -->
@if($errors->userDeletion->isNotEmpty())
<script>
    var myModal = new bootstrap.Modal(document.getElementById('confirmUserDeletion'));
    myModal.show();
</script>
@endif

</section>
