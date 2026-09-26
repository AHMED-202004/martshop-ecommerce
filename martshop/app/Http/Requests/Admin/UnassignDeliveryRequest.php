<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UnassignDeliveryRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPermission('deliveries.manage') === true; }
    public function rules(): array { return ['lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]; }
}
