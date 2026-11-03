<?php

declare(strict_types=1);

namespace Oficina\Identity\Interfaces\Http;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
