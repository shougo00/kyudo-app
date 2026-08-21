<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RegistrationLicenseCode;
use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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
            'password' => ['required', 'confirmed', new PasswordPolicy()],
            'gender' => ['required', 'in:male,female'],
            'license_code' => ['required', 'string', 'max:50'],
        ], [
            'username.required' => 'ユーザー名を入力してください。',
            'username.min' => 'ユーザー名は5文字以上で入力してください。',
            'username.regex' => 'ユーザー名は英数字のみ使用できます。',
            'username.unique' => 'このユーザー名はすでに使われています。',
            'license_code.required' => 'ライセンスコードを入力してください。',
        ]);

        $licenseCode = RegistrationLicenseCode::where('code', RegistrationLicenseCode::normalize($request->license_code))
            ->where('is_active', true)
            ->first();

        if (!$licenseCode) {
            throw ValidationException::withMessages([
                'license_code' => '有効なライセンスコードを入力してください。',
            ]);
        }

        $user = DB::transaction(function () use ($request, $licenseCode) {
            $user = User::create([
                'name' => $request->name,
                'registration_license_code_id' => $licenseCode->id,
                'username' => $request->username,
                'email' => null,
                'password' => Hash::make($request->password),
                'is_admin' => false,
                'gender' => $request->gender,
            ]);

            if ($licenseCode->group_id) {
                DB::table('group_user')->insert([
                    'group_id' => $licenseCode->group_id,
                    'user_id' => $user->id,
                ]);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user, true);

        return redirect()->route('home');
    }
}
