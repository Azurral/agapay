<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends StoreUserRequest
{
    protected $errorBag = 'editUser';

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            ...parent::rules(),
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', $this->uniqueUsername($target->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var User $target */
                $target = $this->route('user');

                $lockingSelfOut = $target->is($this->user()) && (
                    $this->input('status') !== User::STATUS_ACTIVE
                    || (int) $this->input('role_id') !== $target->role_id
                );

                if ($lockingSelfOut) {
                    $validator->errors()->add('role_id', 'You cannot deactivate or change the role of your own account.');
                }
            },
        ];
    }
}
