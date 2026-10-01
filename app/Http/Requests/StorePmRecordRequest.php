<?php

namespace App\Http\Requests;

use App\Enums\Period;
use App\Services\PeriodService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePmRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pm.fill');
    }

    public function rules(): array
    {
        return [
            'machine_id' => ['required', 'exists:machines,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:' . (date('Y') + 1)],
            'period' => ['required', Rule::enum(Period::class)],
            'general_note' => ['nullable', 'string', 'max:1000'],
            'revision_reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.category' => ['required', 'string', 'max:50'],
            'items.*.spec' => ['nullable', 'string', 'max:255'],
            'items.*.actual' => ['required', 'string', 'max:255'],
            'items.*.act_clean' => ['boolean'],
            'items.*.act_repair' => ['boolean'],
            'items.*.act_lubricate' => ['boolean'],
            'items.*.act_replace' => ['boolean'],
            'items.*.final_condition' => ['nullable', 'string', 'max:50'],
            'items.*.parts_replaced' => ['integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $period = Period::tryFrom($this->input('period'));
            $year = (int) $this->input('year');

            if ($period && PeriodService::isFuturePeriod($period, $year)) {
                $validator->errors()->add('period', 'Periode masa depan tidak dapat diisi.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.*.actual.required' => 'Nilai aktual wajib diisi.',
            'items.required' => 'Minimal satu item checklist.',
        ];
    }
}
