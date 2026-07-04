<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:5',
                'max:255',
                'regex:/^[a-zA-Z0-9]+$/',
                'unique:users,username',
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'is_admin' => ['nullable', 'boolean'],
            'gender' => ['required', 'in:male,female'],
        ], [
            'username.required' => 'ユーザー名を入力してください。',
            'username.min' => 'ユーザー名は5文字以上で入力してください。',
            'username.regex' => 'ユーザー名は英数字のみ使用できます。',
            'username.unique' => 'このユーザー名はすでに使われています。',
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => null,
            'password' => Hash::make($request->password),
            'is_admin' => $request->has('is_admin'),
            'gender' => $request->gender,
        ]);

        event(new Registered($user));

        Auth::login($user, true);

        return redirect()->route('home');
    }
}
