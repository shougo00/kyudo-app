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
use App\Models\SystemSetting;
use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemController extends Controller
{
    private const USER_IMPORT_HEADERS = [
        '表示名',
        'ユーザー名',
        'パスワード',
        '性別',
        '学年',
        'ライセンスコード',
    ];

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

    public function userImportTemplate(Request $request): StreamedResponse
    {
        $this->authorizeSystemAdmin($request);

        $filename = 'user_import_template.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::USER_IMPORT_HEADERS);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importUsers(Request $request): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        $request->validate([
            'csv_file' => ['required', 'file', 'max:2048'],
        ], [
            'csv_file.required' => 'CSVファイルを選択してください。',
            'csv_file.file' => 'CSVファイルを選択してください。',
            'csv_file.max' => 'CSVファイルは2MB以内でアップロードしてください。',
        ]);

        [$rows, $errors] = $this->readUserImportCsv($request->file('csv_file')->getRealPath());

        if ($errors) {
            return back()
                ->with('error', 'CSVを取り込めませんでした。内容を確認してください。')
                ->with('import_errors', $errors);
        }

        if ($rows === []) {
            return back()
                ->with('error', '取り込み対象の行がありません。')
                ->with('import_errors', ['2行目以降にユーザー情報を入力してください。']);
        }

        [$rows, $errors] = $this->validateUserImportRows($rows);

        if ($errors) {
            return back()
                ->with('error', 'CSVを取り込めませんでした。内容を確認してください。')
                ->with('import_errors', $errors);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $licenseCode = $row['license_code_model'];

                $user = User::create([
                    'name' => $row['name'],
                    'registration_license_code_id' => $licenseCode->id,
                    'username' => $row['username'],
                    'email' => null,
                    'password' => Hash::make($row['password']),
                    'is_admin' => false,
                    'gender' => $row['gender'],
                    'grade_level' => $row['grade_level'],
                ]);

                if ($licenseCode->group_id) {
                    DB::table('group_user')->insert([
                        'group_id' => $licenseCode->group_id,
                        'user_id' => $user->id,
                    ]);
                }
            }
        });

        return back()->with('success', count($rows) . '件のユーザーを取り込みました。');
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

        $groupCreationDisabled = SystemSetting::bool('group_creation_disabled');

        return view('admin.system.groups', compact('groups', 'search', 'groupCreationDisabled'));
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

    public function updateGroupSettings(Request $request): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        $request->validate([
            'group_creation_disabled' => ['nullable', 'boolean'],
        ]);

        SystemSetting::setBool('group_creation_disabled', $request->boolean('group_creation_disabled'));

        return back()->with('success', 'グループの全体設定を更新しました。');
    }

    public function destroyGroup(Request $request, Group $group): RedirectResponse
    {
        $this->authorizeSystemAdmin($request);

        DB::transaction(fn() => $this->deleteGroupAndMemberships($group));

        return back()->with('success', "{$group->name} を削除し、全員を退会状態にしました。");
    }

    private function readUserImportCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if (!$handle) {
            return [[], ['CSVファイルを開けませんでした。']];
        }

        $headerRow = fgetcsv($handle);

        if ($headerRow === false) {
            fclose($handle);

            return [[], ['CSVのヘッダー行がありません。']];
        }

        $headers = array_map(fn($value) => $this->normalizeCsvHeader($value), $headerRow);
        [$headerMap, $missingHeaders] = $this->userImportHeaderMap($headers);

        if ($missingHeaders) {
            fclose($handle);

            return [[], ['必要な列がありません: ' . implode('、', $missingHeaders)]];
        }

        $rows = [];
        $lineNumber = 1;

        while (($csvRow = fgetcsv($handle)) !== false) {
            $lineNumber++;
            $csvRow = array_map(fn($value) => $this->cleanCsvCell($value), $csvRow);

            if (collect($csvRow)->every(fn($value) => trim((string) $value) === '')) {
                continue;
            }

            $rows[] = [
                'line' => $lineNumber,
                'name' => $csvRow[$headerMap['name']] ?? '',
                'username' => $csvRow[$headerMap['username']] ?? '',
                'password' => $csvRow[$headerMap['password']] ?? '',
                'gender_raw' => $csvRow[$headerMap['gender']] ?? '',
                'grade_level_raw' => $csvRow[$headerMap['grade_level']] ?? '',
                'license_code_raw' => $csvRow[$headerMap['license_code']] ?? '',
            ];
        }

        fclose($handle);

        return [$rows, []];
    }

    private function userImportHeaderMap(array $headers): array
    {
        $aliases = [
            'name' => ['表示名', '名前', 'name'],
            'username' => ['ユーザー名', 'ユーザ名', 'username'],
            'password' => ['パスワード', 'password'],
            'gender' => ['性別', 'gender'],
            'grade_level' => ['学年', 'grade', 'grade_level'],
            'license_code' => ['ライセンスコード', 'license_code', 'license'],
        ];
        $headerMap = [];
        $missing = [];

        foreach ($aliases as $key => $candidates) {
            $index = collect($candidates)
                ->map(fn($candidate) => $this->normalizeCsvHeader($candidate))
                ->map(fn($candidate) => array_search($candidate, $headers, true))
                ->first(fn($candidateIndex) => $candidateIndex !== false);

            if ($index === null) {
                $missing[] = self::USER_IMPORT_HEADERS[
                    array_search($key, ['name', 'username', 'password', 'gender', 'grade_level', 'license_code'], true)
                ];
                continue;
            }

            $headerMap[$key] = (int) $index;
        }

        return [$headerMap, $missing];
    }

    private function validateUserImportRows(array $rows): array
    {
        $errors = [];
        $passwordPolicy = new PasswordPolicy();

        foreach ($rows as $index => $row) {
            $rows[$index]['name'] = trim($row['name']);
            $rows[$index]['username'] = trim($row['username']);
            $rows[$index]['password'] = trim($row['password']);
            $rows[$index]['license_code'] = RegistrationLicenseCode::normalize($row['license_code_raw']);
            $rows[$index]['gender'] = $this->normalizeImportGender($row['gender_raw']);
            $rows[$index]['grade_level'] = $this->normalizeImportGradeLevel($row['grade_level_raw']);
        }

        $licenseCodes = RegistrationLicenseCode::with('group')
            ->whereIn('code', collect($rows)->pluck('license_code')->filter()->unique())
            ->get()
            ->keyBy('code');
        $importUsernames = collect($rows)
            ->pluck('username')
            ->filter()
            ->map(fn($username) => strtolower($username))
            ->unique()
            ->values();
        $existingUsernames = User::query()
            ->when($importUsernames->isNotEmpty(), function ($query) use ($importUsernames) {
                $query->where(function ($query) use ($importUsernames) {
                    foreach ($importUsernames as $username) {
                        $query->orWhereRaw('LOWER(username) = ?', [$username]);
                    }
                });
            }, fn($query) => $query->whereRaw('1 = 0'))
            ->pluck('username')
            ->map(fn($username) => strtolower($username))
            ->all();
        $seenUsernames = [];
        $groupImportCounts = [];

        foreach ($rows as $index => $row) {
            $line = $row['line'];

            if ($row['name'] === '') {
                $errors[] = "{$line}行目: 表示名を入力してください。";
            } elseif (mb_strlen($row['name']) > 255) {
                $errors[] = "{$line}行目: 表示名は255文字以内で入力してください。";
            }

            if ($row['username'] === '') {
                $errors[] = "{$line}行目: ユーザー名を入力してください。";
            } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $row['username'])) {
                $errors[] = "{$line}行目: ユーザー名は英数字のみ使用できます。";
            } elseif (mb_strlen($row['username']) < 5) {
                $errors[] = "{$line}行目: ユーザー名は5文字以上で入力してください。";
            } elseif (mb_strlen($row['username']) > 255) {
                $errors[] = "{$line}行目: ユーザー名は255文字以内で入力してください。";
            } elseif (strtoupper($row['username']) === 'KANRI') {
                $errors[] = "{$line}行目: KANRIは使用できません。";
            } else {
                $lowerUsername = strtolower($row['username']);

                if (in_array($lowerUsername, $existingUsernames, true)) {
                    $errors[] = "{$line}行目: このユーザー名はすでに使われています。";
                } elseif (isset($seenUsernames[$lowerUsername])) {
                    $errors[] = "{$line}行目: CSV内でユーザー名が重複しています。";
                }

                $seenUsernames[$lowerUsername] = true;
            }

            if ($row['password'] === '') {
                $errors[] = "{$line}行目: パスワードを入力してください。";
            } else {
                $passwordError = null;
                $passwordPolicy->validate('password', $row['password'], function ($message) use (&$passwordError) {
                    $passwordError = $message;
                });

                if ($passwordError) {
                    $errors[] = "{$line}行目: {$passwordError}";
                }
            }

            if ($row['gender'] === false) {
                $errors[] = "{$line}行目: 性別は空欄、male、female、男、女のいずれかで入力してください。";
            }

            if ($row['grade_level'] === false) {
                $errors[] = "{$line}行目: 学年は空欄、または1以上の数字で入力してください。";
            }

            $licenseCode = $licenseCodes->get($row['license_code']);

            if ($row['license_code'] === '') {
                $errors[] = "{$line}行目: ライセンスコードを入力してください。";
                continue;
            }

            if (!$licenseCode || !$licenseCode->is_active) {
                $errors[] = "{$line}行目: 有効なライセンスコードを入力してください。";
                continue;
            }

            if ($row['grade_level'] !== false && $row['grade_level'] !== null) {
                $maxGrade = $licenseCode->group?->uses_grades
                    ? max(1, (int) $licenseCode->group->grade_count)
                    : 12;

                if ($row['grade_level'] > $maxGrade) {
                    $errors[] = "{$line}行目: 学年は{$maxGrade}以下で入力してください。";
                }
            }

            $rows[$index]['license_code_model'] = $licenseCode;

            if ($licenseCode->group_id) {
                $groupImportCounts[$licenseCode->group_id] = ($groupImportCounts[$licenseCode->group_id] ?? 0) + 1;
            }
        }

        foreach ($groupImportCounts as $groupId => $importCount) {
            $group = $licenseCodes
                ->first(fn($licenseCode) => (int) $licenseCode->group_id === (int) $groupId)
                ?->group;

            if (!$group || (int) ($group->max_members ?? 0) <= 0) {
                continue;
            }

            $activeMemberCount = DB::table('group_user')
                ->where('group_id', $group->id)
                ->whereNull('deleted_at')
                ->count();
            $maxMembers = (int) $group->max_members;

            if ($activeMemberCount + $importCount > $maxMembers) {
                $errors[] = "グループ「{$group->name}」は最大人数（{$maxMembers}人）を超えます。現在{$activeMemberCount}人、取り込み予定{$importCount}人です。";
            }
        }

        if ($errors) {
            return [[], $errors];
        }

        return [$rows, []];
    }

    private function normalizeImportGender(string $value): string|false|null
    {
        $value = strtolower($this->normalizeLooseText($value));

        return match ($value) {
            '' => null,
            'male', 'm', '男', '男性', '男子' => 'male',
            'female', 'f', '女', '女性', '女子' => 'female',
            default => false,
        };
    }

    private function normalizeImportGradeLevel(string $value): int|false|null
    {
        $value = $this->normalizeLooseText($value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(['学年', '年'], '', $value);

        if (!preg_match('/^[0-9]+$/', $value)) {
            return false;
        }

        $grade = (int) $value;

        return $grade >= 1 ? $grade : false;
    }

    private function normalizeLooseText(string $value): string
    {
        $value = trim($value);

        if (function_exists('mb_convert_kana')) {
            $value = mb_convert_kana($value, 'asKV', 'UTF-8');
        }

        return trim($value);
    }

    private function normalizeCsvHeader($value): string
    {
        return strtolower(str_replace([' ', '　'], '', $this->cleanCsvCell($value)));
    }

    private function cleanCsvCell($value): string
    {
        $value = (string) $value;

        if ($value !== '' && function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'SJIS-win,UTF-8');
        }

        return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value));
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
