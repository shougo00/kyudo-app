@extends('layouts.user')

@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-1">設定</h4>
            <div class="text-muted small">
                {{ $group ? $group->name : 'グループ未参加' }}
            </div>
        </div>
        <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">プロフィールへ</a>
    </div>

    @if(session('status') === 'settings-updated')
        <div class="alert alert-success">グループ設定を保存しました。</div>
    @endif
    @if(session('status') === 'user-settings-updated')
        <div class="alert alert-success">ユーザー設定を保存しました。</div>
    @endif
    @if(session('status') === 'camera-disabled')
        <div class="alert alert-warning">カメラを使用するには、この画面でカメラ設定をオンにしてください。</div>
    @endif
    @if(session('status') === 'grades-promoted')
        <div class="alert alert-success">
            グループ内の学年を1つ上げました。更新人数：{{ session('promoted_count', 0) }}人
        </div>
    @endif

    @php
        $selectedLineupPoolHeightLevel = (int) old('lineup_pool_height_level', $user->lineup_pool_height_level ?? 5);
    @endphp

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <strong>ユーザー設定</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('settings.user.update') }}">
                @csrf
                @method('PATCH')

                <div class="mb-3">
                    <label for="lineup_pool_height_level" class="form-label">立順未配置欄の高さ</label>
                    <select id="lineup_pool_height_level"
                            name="lineup_pool_height_level"
                            class="form-select @error('lineup_pool_height_level') is-invalid @enderror">
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}" {{ $selectedLineupPoolHeightLevel === $i ? 'selected' : '' }}>
                                {{ $i }}
                            </option>
                        @endfor
                    </select>
                    @error('lineup_pool_height_level')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">カメラ</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input"
                               type="checkbox"
                               id="uses_camera"
                               name="uses_camera"
                               value="1"
                               {{ old('uses_camera', $user->uses_camera ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="uses_camera">
                            カメラを使用する
                        </label>
                    </div>
                    <div class="text-muted small mt-1">
                        オフの場合はメニューにカメラを表示しません。
                    </div>
                    @error('uses_camera')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">ホストユーザー</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input"
                               type="checkbox"
                               id="is_admin"
                               name="is_admin"
                               value="1"
                               {{ old('is_admin', $user->is_admin ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_admin">
                            タブレットなどで使用するホストユーザーとしてON
                        </label>
                    </div>
                    <div class="text-muted small mt-1">
                        オンの場合、ログイン後にグループ履歴へ移動します。
                    </div>
                    @error('is_admin')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <button class="btn btn-primary">ユーザー設定を保存</button>
            </form>
        </div>
    </div>

    @if(!$group)
        <div class="alert alert-warning">
            グループ設定は、グループに参加してから使用できます。
        </div>
    @elseif(!$unlocked)
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <strong>管理者確認</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.unlock') }}" class="settings-unlock-form">
                    @csrf

                    <div class="mb-3">
                        <label for="password" class="form-label">管理者パスワード</label>
                        <input id="password"
                               type="password"
                               name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               required
                               autocomplete="current-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button class="btn btn-primary">設定を開く</button>
                </form>
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <strong>グループ設定</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label for="official_tates_per_page" class="form-label">公式記録の1ページ最大立数（グループ設定）</label>
                        <select id="official_tates_per_page"
                                name="official_tates_per_page"
                                class="form-select @error('official_tates_per_page') is-invalid @enderror">
                            @for($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}" {{ (int) old('official_tates_per_page', $group->official_tates_per_page ?? 5) === $i ? 'selected' : '' }}>
                                    {{ $i }}立
                                </option>
                            @endfor
                        </select>
                        @error('official_tates_per_page')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">グループ記録画面の表示（グループ設定）</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="show_group_records_to_members"
                                   name="show_group_records_to_members"
                                   value="1"
                                   {{ old('show_group_records_to_members', $group->show_group_records_to_members ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="show_group_records_to_members">
                                ホスト以外にもグループ的中記録（正規連）を表示する
                            </label>
                        </div>
                        <div class="text-muted small mt-1">
                            オフの場合、ホスト以外のメニューには正規連のグループ記録を表示しません。自主練は常にホストのみ表示します。
                        </div>
                        @error('show_group_records_to_members')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 ms-3 ps-2 border-start">
                        <label class="form-label">ホスト以外の編集権限（グループ設定）</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="allow_members_edit_group_records"
                                   name="allow_members_edit_group_records"
                                   value="1"
                                   {{ old('allow_members_edit_group_records', $group->allow_members_edit_group_records ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="allow_members_edit_group_records">
                                ホスト以外も正規連の立順変更・的中入力をできるようにする
                            </label>
                        </div>
                        <div class="text-muted small mt-1">
                            オフの場合、ホスト以外は閲覧のみになります。
                        </div>
                        @error('allow_members_edit_group_records')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">月間記録の印刷表示（グループ設定）</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="show_monthly_rank_on_print"
                                   name="show_monthly_rank_on_print"
                                   value="1"
                                   {{ old('show_monthly_rank_on_print', $group->show_monthly_rank_on_print ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="show_monthly_rank_on_print">
                                月間記録に順位を表示する
                            </label>
                        </div>
                        <div class="text-muted small mt-1">
                            オンの場合、月間記録を印刷すると一番右に総合的中率順の順位を表示します。
                        </div>
                        @error('show_monthly_rank_on_print')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    @php
                        $gradeColors = old('grade_colors', $group->grade_colors ?? []);
                        $selectedGradeCount = (int) old('grade_count', $group->grade_count ?? 3);
                        $defaultGradeColors = [
                            '#dbeafe',
                            '#fee2e2',
                            '#dcfce7',
                            '#fef3c7',
                            '#ede9fe',
                            '#cffafe',
                            '#fce7f3',
                            '#e5e7eb',
                            '#ffedd5',
                            '#ccfbf1',
                            '#fae8ff',
                            '#e0f2fe',
                        ];
                        $numericScoreOptions = old('numeric_scores', $group->numeric_score_options ?? [
                            ['value' => 1, 'color' => '#dbeafe'],
                            ['value' => 2, 'color' => '#dcfce7'],
                            ['value' => 3, 'color' => '#fef3c7'],
                        ]);
                    @endphp

                    <hr>

                    <div class="mb-3">
                        <label class="form-label">学年表示（グループ設定）</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="uses_grades"
                                   name="uses_grades"
                                   value="1"
                                   {{ old('uses_grades', $group->uses_grades ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="uses_grades">
                                グループで学年を使用する
                            </label>
                        </div>
                        @error('uses_grades')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div id="gradeSettings" class="mb-3">
                        <label for="grade_count" class="form-label">学年数</label>
                        <select id="grade_count"
                                name="grade_count"
                                class="form-select @error('grade_count') is-invalid @enderror">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}" {{ $selectedGradeCount === $i ? 'selected' : '' }}>
                                    {{ $i }}学年
                                </option>
                            @endfor
                        </select>
                        @error('grade_count')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <div class="row g-2 mt-2">
                            @for($grade = 1; $grade <= 12; $grade++)
                                @php
                                    $defaultColor = $defaultGradeColors[($grade - 1) % count($defaultGradeColors)];
                                    $color = $gradeColors[$grade] ?? $gradeColors[(string) $grade] ?? $defaultColor;
                                @endphp
                                <div class="col-6 col-md-4 grade-color-row" data-grade-row="{{ $grade }}">
                                    <label for="grade_color_{{ $grade }}" class="form-label small mb-1">{{ $grade }}学年</label>
                                    <div class="input-group">
                                        <input id="grade_color_{{ $grade }}"
                                               type="color"
                                               name="grade_colors[{{ $grade }}]"
                                               value="{{ $color }}"
                                               class="form-control form-control-color">
                                        <input type="text"
                                               value="{{ $color }}"
                                               class="form-control grade-color-text"
                                               data-color-text="{{ $grade }}"
                                               maxlength="7"
                                               inputmode="text">
                                    </div>
                                    @error("grade_colors.$grade")
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endfor
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label">数字モード設定（グループ設定）</label>
                        <div class="text-muted small mb-2">
                            的中記録の数字モードで切り替える数字と背景色です。
                        </div>

                        <div id="numericScoreSettings" class="vstack gap-2">
                            @foreach($numericScoreOptions as $index => $option)
                                <div class="row g-2 align-items-end numeric-score-row">
                                    <div class="col-5">
                                        <label class="form-label small mb-1">数字</label>
                                        <input type="number"
                                               name="numeric_scores[{{ $index }}][value]"
                                               value="{{ $option['value'] ?? '' }}"
                                               min="0"
                                               max="999"
                                               class="form-control">
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label small mb-1">色</label>
                                        <input type="color"
                                               name="numeric_scores[{{ $index }}][color]"
                                               value="{{ $option['color'] ?? '#dbeafe' }}"
                                               class="form-control form-control-color">
                                    </div>
                                    <div class="col-2">
                                        <button type="button" class="btn btn-outline-danger w-100" onclick="removeNumericScoreRow(this)">×</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="addNumericScoreRow()">
                            枠を追加
                        </button>
                    </div>

                    <button class="btn btn-primary">保存</button>
                </form>

                @if($group->uses_grades)
                    <hr>

                    <form method="POST"
                          action="{{ route('settings.promote-grades') }}"
                          onsubmit="return confirm('グループ内の学年を1つ上げますか？最大学年を超えるユーザーの学年は未設定になります。')">
                        @csrf
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <div>
                                <strong>学年の一括更新</strong>
                                <div class="text-muted small">
                                    グループ内の学年を1つ上げます。{{ $group->grade_count }}学年を超えたユーザーは未設定になります。
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary">
                                学年を1つ上げる
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('uses_grades');
    const settings = document.getElementById('gradeSettings');
    const count = document.getElementById('grade_count');
    const showGroupRecordsToggle = document.getElementById('show_group_records_to_members');
    const editGroupRecordsToggle = document.getElementById('allow_members_edit_group_records');

    function refreshGradeSettings() {
        if (!settings || !toggle || !count) return;

        settings.style.display = toggle.checked ? '' : 'none';
        const visibleCount = parseInt(count.value || '1', 10);

        document.querySelectorAll('[data-grade-row]').forEach(row => {
            row.style.display = parseInt(row.dataset.gradeRow, 10) <= visibleCount ? '' : 'none';
        });
    }

    document.querySelectorAll('input[type="color"][name^="grade_colors"]').forEach(input => {
        const grade = input.id.replace('grade_color_', '');
        const text = document.querySelector(`[data-color-text="${grade}"]`);

        input.addEventListener('input', () => {
            if (text) text.value = input.value;
        });

        if (text) {
            text.addEventListener('input', () => {
                if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) {
                    input.value = text.value;
                }
            });
        }
    });

    if (toggle) toggle.addEventListener('change', refreshGradeSettings);
    if (count) count.addEventListener('change', refreshGradeSettings);

    refreshGradeSettings();

    function refreshGroupRecordEditSetting() {
        if (!showGroupRecordsToggle || !editGroupRecordsToggle) return;

        editGroupRecordsToggle.disabled = !showGroupRecordsToggle.checked;
        if (!showGroupRecordsToggle.checked) {
            editGroupRecordsToggle.checked = false;
        }
    }

    if (showGroupRecordsToggle) {
        showGroupRecordsToggle.addEventListener('change', refreshGroupRecordEditSetting);
    }
    refreshGroupRecordEditSetting();
});

function addNumericScoreRow() {
    const wrap = document.getElementById('numericScoreSettings');
    if (!wrap) return;

    const index = wrap.querySelectorAll('.numeric-score-row').length;
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-end numeric-score-row';
    row.innerHTML = `
        <div class="col-5">
            <label class="form-label small mb-1">数字</label>
            <input type="number" name="numeric_scores[${index}][value]" min="0" max="999" class="form-control">
        </div>
        <div class="col-5">
            <label class="form-label small mb-1">色</label>
            <input type="color" name="numeric_scores[${index}][color]" value="#dbeafe" class="form-control form-control-color">
        </div>
        <div class="col-2">
            <button type="button" class="btn btn-outline-danger w-100" onclick="removeNumericScoreRow(this)">×</button>
        </div>
    `;
    wrap.appendChild(row);
}

function removeNumericScoreRow(button) {
    const row = button.closest('.numeric-score-row');
    if (row) row.remove();
}
</script>
@endsection
