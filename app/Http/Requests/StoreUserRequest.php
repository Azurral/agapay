<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    protected $errorBag = 'createUser';

    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'regex:/^[A-Za-z]\.?$/'],
            'suffix' => ['nullable', Rule::in(User::SUFFIXES)],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', $this->uniqueUsername()],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Use letters, numbers, dots, dashes or underscores only.',
            'middle_initial.regex' => 'Type one letter only, e.g. M.',
            'suffix.in' => 'Choose Jr., Sr., II, III, IV or V, or leave it blank.',
        ];
    }

    /** Usernames are unique regardless of letter case (login is case-insensitive). */
    protected function uniqueUsername(?int $ignoreId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreId) {
            $taken = User::whereRaw('LOWER(username) = ?', [Str::lower((string) $value)])
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if ($taken) {
                $fail('This username is already taken.');
            }
        };
    }
}
