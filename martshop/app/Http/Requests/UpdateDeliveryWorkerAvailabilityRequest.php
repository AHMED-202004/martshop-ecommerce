<?php

namespace App\Http\Requests;

use App\Enums\DeliveryWorkerAvailability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryWorkerAvailabilityRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return ['availability' => ['required', Rule::enum(DeliveryWorkerAvailability::class)], 'reason' => ['nullable', 'string', 'max:500']];
    }
}
