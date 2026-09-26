<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RecordDeliveryOutcomeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPermission('deliveries.manage') ?? false; }
    public function rules(): array { return ['status' => ['required', Rule::in(['failed', 'returned', 'cancelled'])], 'reason' => ['required', 'string', 'min:5', 'max:1000'], 'lock_version' => ['required', 'integer', 'min:0']]; }
}
