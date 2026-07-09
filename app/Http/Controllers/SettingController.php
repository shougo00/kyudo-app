<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(Request $request): View
    {
        $group = $this->currentGroup($request);
        $user = $request->user();
        $unlocked = $group && $request->session()->get($this->sessionKey($group->id), false);

        return view('settings.index', compact('group', 'user', 'unlocked'));
    }

    public function unlock(Request $request): RedirectResponse
    {
        $group = $this->currentGroup($request);

        if (!$group) {
            return back()->withErrors(['password' => 'グループに参加していません。']);
        }

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if ($request->password !== 'kyudo') {
            return back()->withErrors(['password' => '管理者パスワードが正しくありません。']);
        }

        $request->session()->put($this->sessionKey($group->id), true);

        return redirect()->route('settings.index');
    }

    public function update(Request $request): RedirectResponse
    {
        $group = $this->currentGroup($request);

        if (!$group) {
            return back()->withErrors(['official_tates_per_page' => 'グループに参加していません。']);
        }

        if (!$request->session()->get($this->sessionKey($group->id), false)) {
            return redirect()->route('settings.index');
        }

        $validated = $request->validate([
            'official_tates_per_page' => ['required', 'integer', 'min:1', 'max:10'],
            'uses_grades' => ['nullable', 'boolean'],
            'grade_count' => ['required', 'integer', 'min:1', 'max:12'],
            'grade_colors' => ['array'],
            'grade_colors.*' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'numeric_scores' => ['array'],
            'numeric_scores.*.value' => ['nullable', 'integer', 'min:0', 'max:999'],
            'numeric_scores.*.color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $gradeCount = (int) $validated['grade_count'];
        $gradeColors = [];

        for ($grade = 1; $grade <= $gradeCount; $grade++) {
            $gradeColors[$grade] = $validated['grade_colors'][$grade] ?? $this->defaultGradeColor($grade);
        }

        $numericScoreOptions = collect($validated['numeric_scores'] ?? [])
            ->map(function ($option) {
                if (($option['value'] ?? '') === '' || empty($option['color'])) {
                    return null;
                }

                return [
                    'value' => (int) $option['value'],
                    'color' => $option['color'],
                ];
            })
            ->filter()
            ->unique('value')
            ->values()
            ->all();

        $group->update([
            'official_tates_per_page' => $validated['official_tates_per_page'],
            'uses_grades' => $request->boolean('uses_grades'),
            'grade_count' => $gradeCount,
            'grade_colors' => $gradeColors,
            'numeric_score_options' => count($numericScoreOptions) > 0 ? $numericScoreOptions : $this->defaultNumericScoreOptions(),
        ]);

        return back()->with('status', 'settings-updated');
    }

    public function updateUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lineup_pool_height_level' => ['required', 'integer', 'min:1', 'max:10'],
            'uses_camera' => ['nullable', 'boolean'],
        ]);

        $request->user()->update([
            'lineup_pool_height_level' => $validated['lineup_pool_height_level'],
            'uses_camera' => $request->boolean('uses_camera'),
        ]);

        return back()->with('status', 'user-settings-updated');
    }

    public function promoteGrades(Request $request): RedirectResponse
    {
        $group = $this->currentGroup($request);

        if (!$group) {
            return back()->withErrors(['grade_level' => 'グループに参加していません。']);
        }

        if (!$request->session()->get($this->sessionKey($group->id), false)) {
            return redirect()->route('settings.index');
        }

        if (!$group->uses_grades) {
            return back()->withErrors(['grade_level' => '学年表示が有効になっていません。']);
        }

        $maxGrade = max(1, (int) ($group->grade_count ?? 1));
        $promotedCount = 0;

        $group->users()
            ->where('is_admin', false)
            ->whereNotNull('grade_level')
            ->get()
            ->each(function ($user) use ($maxGrade, &$promotedCount) {
                $currentGrade = (int) $user->grade_level;
                $nextGrade = $currentGrade + 1 > $maxGrade ? null : $currentGrade + 1;

                $user->update(['grade_level' => $nextGrade]);
                $promotedCount++;
            });

        return back()
            ->with('status', 'grades-promoted')
            ->with('promoted_count', $promotedCount);
    }

    private function currentGroup(Request $request): ?Group
    {
        return $request->user()
            ? $request->user()->groups()->with('host')->first()
            : null;
    }

    private function sessionKey(int $groupId): string
    {
        return "settings_unlocked_group_{$groupId}";
    }

    private function defaultGradeColor(int $grade): string
    {
        $colors = [
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

        return $colors[($grade - 1) % count($colors)];
    }

    private function defaultNumericScoreOptions(): array
    {
        return [
            ['value' => 1, 'color' => '#dbeafe'],
            ['value' => 2, 'color' => '#dcfce7'],
            ['value' => 3, 'color' => '#fef3c7'],
        ];
    }
}
