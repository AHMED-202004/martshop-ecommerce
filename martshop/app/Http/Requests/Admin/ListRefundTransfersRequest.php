<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRefundTransfersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('refunds.pay');
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['approved', 'processing', 'paid'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'cancelled_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function attributes(): array
    {
        return ['q' => 'مرجع الاسترداد', 'status' => 'الحالة', 'from' => 'من تاريخ', 'to' => 'إلى تاريخ',
            'sort' => 'الترتيب', 'cancelled_only' => 'المحاولات الملغاة', 'page' => 'رقم الصفحة'];
    }
}
