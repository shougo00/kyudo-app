<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Avatar;
use App\Models\Group;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    private const INVITE_CODE_LENGTH = 5;
    private const INVITE_CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const GROUP_CREATION_DISABLED_MESSAGE = 'グループ作成にはライセンスが必要です。管理者にご相談ください。';
    private const LICENSE_GROUP_LOCK_MESSAGE = 'このライセンスでは指定されたグループにのみ参加できます。';

    // 一覧
    public function index()
    {
        $userAvatarRelations = array_map(
            fn(string $relation): string => 'users.' . $relation,
            Avatar::itemRelations()
        );

        $groups = auth()->user()->groups()
            ->with(array_merge(['users.avatar'], $userAvatarRelations))
            ->get();

        return view('groups.index', compact('groups'));
    }
    // 作成画面
    public function create()
    {
        if ($this->groupCreationDisabled()) {
            return redirect('/groups')->with('error', self::GROUP_CREATION_DISABLED_MESSAGE);
        }

        if ($this->licenseLockedGroupId(auth()->user())) {
            return redirect('/groups')->with('error', self::LICENSE_GROUP_LOCK_MESSAGE);
        }

        return view('groups.create');
    }

    // 作成処理
    public function store(Request $request)
    {
        if ($this->groupCreationDisabled()) {
            return redirect('/groups')->with('error', self::GROUP_CREATION_DISABLED_MESSAGE);
        }

        if ($this->licenseLockedGroupId(auth()->user())) {
            return redirect('/groups')->with('error', self::LICENSE_GROUP_LOCK_MESSAGE);
        }

        // ★すでにグループに所属してるかチェック
        if (auth()->user()->groups()->exists()) {
            return back()->with('error', 'すでにグループに参加しています');
        }

        $request->validate([
            'name' => 'required'
        ]);

        $group = Group::create([
            'name' => $request->name,
            'host_user_id' => auth()->id(),
            'invite_code' => $this->generateInviteCode(),
        ]);

        // 作成者も参加
        DB::table('group_user')->insert([
            'group_id' => $group->id,
            'user_id' => auth()->id(),
        ]);
        // 作成者をホストユーザーにする
        auth()->user()->update([
            'is_admin' => true,
        ]);

        return redirect('/groups');
    }

    // 参加画面
    public function joinForm()
    {
        return view('groups.join');
    }

    // 参加処理
   public function join(Request $request)
    {
        $request->merge([
            'invite_code' => strtoupper(trim((string) $request->invite_code)),
        ]);

        $request->validate([
            'invite_code' => ['required', 'regex:/^[A-Z0-9]{5}$/'],
        ], [
            'invite_code.required' => '招待コードを入力してください',
            'invite_code.regex' => '招待コードは5桁の英数字で入力してください',
        ]);

        $group = Group::where('invite_code', $request->invite_code)->first();

        if (!$group) {
            return back()->with('error', 'グループが見つかりません');
        }

        $licensedGroupId = $this->licenseLockedGroupId(auth()->user());

        if ($licensedGroupId && (int) $group->id !== $licensedGroupId) {
            return back()->with('error', self::LICENSE_GROUP_LOCK_MESSAGE);
        }

        $userId = auth()->id();
        $activeOtherMembership = DB::table('group_user')
            ->where('user_id', $userId)
            ->where('group_id', '<>', $group->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($activeOtherMembership) {
            return back()->with('error', 'すでに別のグループに参加しています');
        }

        $hasActiveMembership = DB::table('group_user')
            ->where('group_id', $group->id)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->exists();
        $membership = DB::table('group_user')
            ->where('group_id', $group->id)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();

        if ($hasActiveMembership) {
            return back()->with('error', '既に参加しています');
        }

        if ($this->groupIsFull($group)) {
            return back()->with('error', "このグループは最大人数（{$group->max_members}人）に達しています");
        }

        if ($membership) {
            DB::table('group_user')
                ->where('id', $membership->id)
                ->update(['deleted_at' => null]);
        } else {
            DB::table('group_user')->insert([
                'group_id' => $group->id,
                'user_id' => $userId,
            ]);
        }

        return redirect('/groups');
    }

    private function generateInviteCode(): string
    {
        do {
            $code = collect(range(1, self::INVITE_CODE_LENGTH))
                ->map(fn() => self::INVITE_CODE_ALPHABET[random_int(0, strlen(self::INVITE_CODE_ALPHABET) - 1)])
                ->implode('');
        } while (Group::where('invite_code', $code)->exists());

        return $code;
    }

    private function groupCreationDisabled(): bool
    {
        return SystemSetting::bool('group_creation_disabled');
    }

    private function groupIsFull(Group $group): bool
    {
        $maxMembers = (int) ($group->max_members ?? 0);

        if ($maxMembers <= 0) {
            return false;
        }

        $activeMemberCount = DB::table('group_user')
            ->where('group_id', $group->id)
            ->whereNull('deleted_at')
            ->count();

        return $activeMemberCount >= $maxMembers;
    }

    private function licenseLockedGroupId($user): ?int
    {
        if (!$user) {
            return null;
        }

        $user->loadMissing('registrationLicenseCode');

        return $user->registrationLicenseCode?->group_id
            ? (int) $user->registrationLicenseCode->group_id
            : null;
    }

    public function leave(Group $group)
    {
        $userId = auth()->id();

        // ホストは脱退禁止（重要）
        if ($group->host_user_id === $userId) {
            return back()->with('error', 'ホストはグループを抜けられません');
        }

        DB::table('group_user')
            ->where('group_id', $group->id)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
        
        // グループを抜けたら一般ユーザーに戻す
        auth()->user()->update([
            'is_admin' => false,
        ]);

        return redirect('/groups')->with('success', 'グループを抜けました');
    }

    public function removeMember(Request $request, Group $group, int $user)
    {
        if ((int) $group->host_user_id !== (int) auth()->id()) {
            abort(403, 'ホストだけがメンバーを退会させられます');
        }

        if ((int) $group->host_user_id === (int) $user) {
            return back()->with('error', 'ホスト自身は退会させられません');
        }

        if (!$group->users()->where('users.id', $user)->exists()) {
            return back()->with('error', 'メンバーが見つかりません');
        }

        DB::table('group_user')
            ->where('group_id', $group->id)
            ->where('user_id', $user)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        \App\Models\User::where('id', $user)->update([
            'is_admin' => false,
        ]);

        return back()->with('success', 'メンバーを退会させました');
    }
}
