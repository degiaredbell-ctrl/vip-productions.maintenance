<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('machine.manage');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('machines', 'code')->ignore($this->machine?->id)],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'sub_category' => ['nullable', 'string', 'max:20'],
            'type' => ['required', Rule::enum(\App\Enums\MachineType::class)],
            'week_group' => ['required', 'integer', 'min:1', 'max:9'],
            'template_id' => ['nullable', 'exists:checklist_templates,id'],
            'is_active' => ['boolean'],
        ];
    }
}
