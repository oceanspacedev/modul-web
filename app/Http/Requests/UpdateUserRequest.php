<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends StoreUserRequest
{
    public function record(): ?User
    {
        return $this->route('user');
    }

    /**
     * Leaving the password empty keeps the current one.
     *
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return ['nullable', 'string', 'max:255', Password::defaults()];
    }
}
