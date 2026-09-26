<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
class SaveDeliverySlaRuleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPermission('deliveries.manage') ?? false; }
    protected function prepareForValidation(): void { $this->merge(['is_active' => $this->boolean('is_active')]); }
    public function rules(): array { return [
        'name' => ['required', 'string', 'min:3', 'max:120'], 'origin_location_id' => ['nullable', 'integer', 'exists:locations,id'],
        'destination_area' => ['nullable', 'string', 'max:120'], 'order_type' => ['nullable', 'in:standard,express,scheduled'],
        'delivery_method' => ['nullable', 'in:courier,pickup_point,third_party'], 'minimum_distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        'maximum_distance_km' => ['nullable', 'numeric', 'gte:minimum_distance_km', 'max:10000'], 'target_minutes' => ['required', 'integer', 'between:5,10080'],
        'priority' => ['required', 'integer', 'between:-1000,1000'], 'is_active' => ['required', 'boolean'],
        'reason' => ['required', 'string', 'min:5', 'max:500'], 'current_password' => ['required', 'current_password:web'],
    ]; }
}
