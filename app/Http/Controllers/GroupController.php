<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Avatar;
use App\Models\Group;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
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
        return view('groups.create');
    }

    // 作成処理
    public function store(Request $request)
    {
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
            'invite_code' => trim((string) $request->invite_code),
        ]);

        $request->validate([
            'invite_code' => ['required', 'regex:/^\d{4}$/'],
        ], [
            'invite_code.required' => '招待コードを入力してください',
            'invite_code.regex' => '招待コードは4桁の数字で入力してください',
        ]);

        $group = Group::where('invite_code', $request->invite_code)->first();

        if (!$group) {
            return back()->with('error', 'グループが見つかりません');
        }

        $userId = auth()->id();
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
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Group::where('invite_code', $code)->exists());

        return $code;
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
