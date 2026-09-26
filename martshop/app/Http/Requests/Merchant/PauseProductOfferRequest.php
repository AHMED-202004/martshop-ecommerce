<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class PauseProductOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pause', $this->route('offer')) === true;
    }

    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
