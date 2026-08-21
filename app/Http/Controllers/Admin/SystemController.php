<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use App\Models\Group;
use App\Models\KyudoResult;
use App\Models\Lineup;
use App\Models\MatchTeam;
use App\Models\News;
use App\Models\Record;
use App\Models\RegistrationLicenseCode;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SplFileObject;

class SystemController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSystemAdmin($request);

        $logFiles = $this->logFiles();
        $selectedLog = $this->selectedLog($request, $logFiles);

        return view('admin.system.index', [
            'stats' => $this->stats(),
            'recentUsers' => User::latest()->limit(8)->get(),
            'recentGroups' => Group::with(['host', 'users'])->latest()->limit(8)->get(),
            'recentRecords' => Record::with('user')->latest()->limit(10)->get(),
            'recentLicenseCodes' => RegistrationLicenseCode::withCount('users')->latest()->limit(8)->get(),
            'recentContactInquiries' => ContactInquiry::latest()->limit(5)->get(),
            'logFiles' => $logFiles,
            'selectedLog' => $selectedLog,
            'logLines' => $selectedLog ? $this->tailLog($selectedLog['path']) : [],
        ]);
    }

    public function inquiries(Request $request): View
    {
        $this->authorizeSystemAdmin($request);

        $search = trim((string) $request->query('search', ''));

        $inquiries = ContactInquiry::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('group_name', 'like', "%{$search}%")
                        ->orWhere('representative_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.system.inquiries', compact('inquiries', 'search'));
    }

    public function users(Request $request): View
    {
        $this->authorizeSystemAdmin($request);

        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->withCount('groups')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.system.users', compact('users', 'search'));
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:4'],
        ]);

        if ($user->username === 'KANRI') {
            $validated['username'] = 'KANRI';
        }

        $user->name = $validated['name'];
        $user->username = $validated['username'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return back()->with('success', "{$user->name} の情報を更新しました。");
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        if ($user->username === 'KANRI' || $request->user()->is($user)) {
            return back()->with('error', 'KANRIアカウントは削除できません。');
        }

        DB::transaction(function () use ($user) {
            Group::where('host_user_id', $user->id)
                ->get()
                ->each(fn(Group $group) => $this->deleteGroupAndMemberships($group));

            DB::table('avatars')->where('user_id', $user->id)->delete();
            DB::table('group_user')->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            if ($user->email) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }

            $user->delete();
        });

        return back()->with('success', "{$user->name} のアカウントを削除しました。");
    }

    public function groups(Request $request): View
    {
        $this->authorizeSystemAdmin($request);

        $search = trim((string) $request->query('search', ''));

        $groups = Group::query()
            ->with('host')
            ->withCount('users')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('invite_code', 'like', "%{$search}%")
                        ->orWhereHas('host', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.system.groups', compact('groups', 'search'));
    }

    public function licenseCodes(Request $request): View
    {
        $this->authorizeSystemAdmin($request);

        $search = trim((string) $request->query('search', ''));

        $licenseCodes = RegistrationLicenseCode::query()
            ->with(['creator', 'group'])
            ->withCount('users')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('memo', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $groups = Group::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'invite_code']);

        return view('admin.system.license_codes', compact('licenseCodes', 'search', 'groups'));
    }

    public function storeLicenseCode(Request $request): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        $validated = $this->validateLicenseCode($request);

        RegistrationLicenseCode::create([
            'code' => RegistrationLicenseCode::normalize($validated['code']),
            'memo' => $validated['memo'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'group_id' => $validated['group_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'ライセンスコードを追加しました。');
    }

    public function updateLicenseCode(Request $request, RegistrationLicenseCode $licenseCode): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        $validated = $this->validateLicenseCode($request, $licenseCode);

        $licenseCode->update([
            'code' => RegistrationLicenseCode::normalize($validated['code']),
            'memo' => $validated['memo'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'group_id' => $validated['group_id'] ?? null,
        ]);

        return back()->with('success', 'ライセンスコードを更新しました。');
    }

    public function destroyLicenseCode(Request $request, RegistrationLicenseCode $licenseCode): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        if ($licenseCode->users()->exists()) {
            return back()->with('error', '使用済みのライセンスコードは削除できません。無効化してください。');
        }

        $licenseCode->delete();

        return back()->with('success', 'ライセンスコードを削除しました。');
    }

    public function updateGroup(Request $request, Group $group): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        if ($request->has('invite_code')) {
            $request->merge([
                'invite_code' => strtoupper(trim((string) $request->input('invite_code'))),
            ]);
        }

        $validated = $request->validate([
            'invite_code' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[A-Z0-9]{5}$/',
                Rule::unique('groups', 'invite_code')->ignore($group->id),
            ],
            'max_members' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
        ], [
            'invite_code.required' => '招待コードを入力してください。',
            'invite_code.regex' => '招待コードは5桁の英数字で入力してください。',
            'invite_code.unique' => 'この招待コードはすでに使用されています。',
            'max_members.integer' => '最大人数は数字で入力してください。',
            'max_members.min' => '最大人数は1人以上で入力してください。',
            'max_members.max' => '最大人数は999人以下で入力してください。',
        ]);

        $updates = [];

        if (array_key_exists('invite_code', $validated)) {
            $updates['invite_code'] = $validated['invite_code'];
        }

        if (array_key_exists('max_members', $validated)) {
            $updates['max_members'] = $validated['max_members'] ?? null;
        }

        if ($updates) {
            $group->update($updates);
        }

        return back()->with('success', "{$group->name} の設定を更新しました。");
    }

    public function destroyGroup(Request $request, Group $group): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        DB::transaction(fn() => $this->deleteGroupAndMemberships($group));

        return back()->with('success', "{$group->name} を削除し、全員を退会状態にしました。");
    }

    private function authorizeSystemAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || $user->username !== 'KANRI') {
            abort(403, 'システム管理者だけがアクセスできます');
        }
    }

    private function stats(): array
    {
        return [
            'users' => User::count(),
            'normal_users' => User::where('is_admin', false)->count(),
            'admins' => User::where('is_admin', true)->count(),
            'groups' => Group::count(),
            'license_codes' => RegistrationLicenseCode::count(),
            'active_license_codes' => RegistrationLicenseCode::where('is_active', true)->count(),
            'records' => Record::count(),
            'shots' => Shot::count(),
            'lineups' => Lineup::count(),
            'match_teams' => MatchTeam::count(),
            'kyudo_results' => KyudoResult::count(),
            'news' => News::count(),
            'contact_inquiries' => ContactInquiry::count(),
            'sessions' => $this->tableCount('sessions'),
            'jobs' => $this->tableCount('jobs'),
        ];
    }

    private function validateLicenseCode(Request $request, ?RegistrationLicenseCode $licenseCode = null): array
    {
        $request->merge([
            'code' => RegistrationLicenseCode::normalize((string) $request->input('code', '')),
        ]);

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('registration_license_codes', 'code')->ignore($licenseCode?->id),
            ],
            'memo' => ['nullable', 'string', 'max:255'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'ライセンスコードを入力してください。',
            'code.regex' => 'ライセンスコードは英数字とハイフンのみ使用できます。',
            'code.unique' => 'このライセンスコードはすでに登録されています。',
            'memo.max' => 'メモは255文字以内で入力してください。',
            'group_id.exists' => '紐づけるグループを選び直してください。',
        ]);
    }

    private function tableCount(string $table): int
    {
        return DB::getSchemaBuilder()->hasTable($table)
            ? DB::table($table)->count()
            : 0;
    }

    private function logFiles(): array
    {
        return collect(File::glob(storage_path('logs/*.log')) ?: [])
            ->map(fn($path) => [
                'name' => basename($path),
                'path' => $path,
                'size' => File::size($path),
                'updated_at' => date('Y-m-d H:i:s', File::lastModified($path)),
            ])
            ->sortByDesc('updated_at')
            ->values()
            ->all();
    }

    private function deleteGroupAndMemberships(Group $group): void
    {
        DB::table('group_user')->where('group_id', $group->id)->delete();
        $group->delete();
    }

    private function selectedLog(Request $request, array $logFiles): ?array
    {
        if (empty($logFiles)) {
            return null;
        }

        $requested = basename((string) $request->query('log', ''));

        return collect($logFiles)->firstWhere('name', $requested) ?? $logFiles[0];
    }

    private function tailLog(string $path, int $lines = 300): array
    {
        if (!File::exists($path) || !File::isFile($path)) {
            return [];
        }

        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $lines);
        $result = [];

        for ($line = $start; $line <= $lastLine; $line++) {
            $file->seek($line);
            $text = rtrim((string) $file->current(), "\r\n");

            if ($text !== '') {
                $result[] = $text;
            }
        }

        return $result;
    }
}
