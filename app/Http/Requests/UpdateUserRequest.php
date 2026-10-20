<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends StoreUserRequest
{
    protected function uniqueUsername(): mixed
    {
        return Rule::unique('users', 'username')->ignore($this->route('user'));
    }

    /**
     * Leaving the password empty keeps the current one.
     *
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return ['nullable', 'string', Password::defaults()];
    }

    protected function roleRequirement(): string
    {
        return 'nullable';
    }
}
