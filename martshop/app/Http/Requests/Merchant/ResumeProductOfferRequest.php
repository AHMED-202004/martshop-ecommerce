<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class ResumeProductOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('resume', $this->route('offer')) === true;
    }

    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer', 'min:0']];
    }
}
