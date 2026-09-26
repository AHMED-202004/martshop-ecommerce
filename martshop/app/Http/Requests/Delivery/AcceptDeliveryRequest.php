<?php

namespace App\Http\Requests\Delivery;

use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;

class AcceptDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $delivery = $this->route('delivery');

        return $delivery instanceof Delivery && ($this->user()?->can('accept', $delivery) ?? false);
    }

    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer', 'min:0']];
    }
}
