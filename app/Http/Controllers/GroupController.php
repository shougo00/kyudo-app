<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    // 一覧
    public function index()
    {
        $groups = auth()->user()->groups()->with('users.avatar')->get();

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
            'invite_code' => Str::random(12), // 少し長めに
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
        $request->validate([
            'invite_code' => 'required'
        ]);

        $group = Group::where('invite_code', $request->invite_code)->first();

        if (!$group) {
            return back()->with('error', 'グループが見つかりません');
        }

        // ★ここ追加（重複参加防止）
        if ($group->users()->where('user_id', auth()->id())->exists()) {
            return back()->with('error', '既に参加しています');
        }

        // 参加処理
        DB::table('group_user')->insert([
            'group_id' => $group->id,
            'user_id' => auth()->id(),
        ]);

        return redirect('/groups');
    }

    public function leave(Group $group)
    {
        $userId = auth()->id();

        // ホストは脱退禁止（重要）
        if ($group->host_user_id === $userId) {
            return back()->with('error', 'ホストはグループを抜けられません');
        }

        // 中間テーブルから削除
        DB::table('group_user')
            ->where('group_id', $group->id)
            ->where('user_id', $userId)
            ->delete();
        
        // グループを抜けたら一般ユーザーに戻す
        auth()->user()->update([
            'is_admin' => false,
        ]);

        return redirect('/groups')->with('success', 'グループを抜けました');
    }
}