<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\LevelService;

class UserStatusController extends Controller
{
    public function index()
    {
        $users = User::where('is_admin', 0) // 管理者は除外
             ->orderBy('id')
             ->paginate(10);
        return view('admin.users.status_index', compact('users'));
    }

    public function edit(User $user)
    {
        return view('admin.users.status_edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'level' => 'required|integer|min:1',
            'exp' => 'required|integer|min:0',
            'next_exp' => 'required|integer|min:1',
            'point' => 'required|integer|min:0',
        ]);

        $user->update($request->only([
            'level', 'exp', 'next_exp', 'point'
        ]));

        return redirect()
            ->route('admin.users.status.index')
            ->with('success', '更新しました');
    }
    

    public function addExp(Request $request, User $user)
    {
        $request->validate([
            'exp' => 'required|integer|min:1',
        ]);

        LevelService::addExp($user, $request->exp);

        return back()->with('success', '経験値を付与しました');
    }

}
