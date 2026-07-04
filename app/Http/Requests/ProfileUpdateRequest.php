<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $group = $this->user()?->groups()->first();
        $gradeRules = ['nullable', 'integer', 'min:1', 'max:12'];

        if ($group?->uses_grades) {
            $gradeRules = ['nullable', 'integer', 'min:1', 'max:' . max(1, (int) $group->grade_count)];
        }

        return [
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($this->user()->id),
            ],

            'is_admin' => ['nullable', 'boolean'],
            'gender' => ['required', 'in:male,female'],
            'grade_level' => $gradeRules,
        ];
    }
}
