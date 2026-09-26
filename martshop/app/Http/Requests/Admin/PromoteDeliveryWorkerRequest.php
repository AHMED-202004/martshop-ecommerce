<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PromoteDeliveryWorkerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('deliveries.manage') ?? false;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:191', 'exists:users,email']];
    }
}
