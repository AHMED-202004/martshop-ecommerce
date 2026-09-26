<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RecordDeliveryDelayRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPermission('deliveries.manage') || $this->user()?->hasPermission('deliveries.update-status'); }
    public function rules(): array { return [
        'reason' => ['required', Rule::in(['merchant_not_ready','customer_unavailable','traffic','access_issue','area_disruption','weather','vehicle_issue','courier_delay','incorrect_address','other'])],
        'responsibility' => ['required', Rule::in(['merchant','delivery_worker','customer','platform','external_condition'])],
        'note' => ['nullable', 'string', 'max:1000'], 'lock_version' => ['required', 'integer', 'min:0'],
    ]; }
}
