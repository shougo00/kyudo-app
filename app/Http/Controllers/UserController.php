<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    // ユーザー一覧
    public function index()
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }

    // ユーザー削除
    public function destroy(User $user)
    {
        // 管理者が自分を削除しないようにする場合
        if (auth()->id() === $user->id) {
            return redirect()->back()->with('error', '自分のアカウントは削除できません。');
        }

        $user->delete();

        return redirect()->back()->with('success', $user->name . ' を削除しました。');
    }

    // パスワードリセット
    public function resetPassword(User $user)
    {
        $newPassword = \Illuminate\Support\Str::random(10);
        $user->password = \Illuminate\Support\Facades\Hash::make($newPassword);
        $user->save();

        return redirect()->back()->with('success', $user->name . ' のパスワードをリセットしました。新しいパスワード: ' . $newPassword);
    }
}
