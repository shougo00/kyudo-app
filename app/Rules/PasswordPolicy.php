<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordPolicy implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (strlen($password) >= 8) {
            return;
        }

        if (preg_match('/^\d{5,}$/', $password)) {
            return;
        }

        $fail('パスワードは8文字以上、または数字のみ5桁以上で入力してください。');
    }
}
