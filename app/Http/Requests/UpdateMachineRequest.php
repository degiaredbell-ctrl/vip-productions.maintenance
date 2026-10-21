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
            'code' => ['required', 'string', 'max:20', Rule::unique('assets', 'code')->ignore($this->machine?->id)],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:100', Rule::exists('locations', 'name')],
            'category' => ['nullable', 'string', 'max:50'],
            'sub_category' => ['nullable', 'string', 'max:20'],
            'type' => ['required', 'string', Rule::exists('machine_types', 'code')],
            'week_group' => ['required', 'integer', 'min:1', 'max:9'],
            'template_id' => ['nullable', 'exists:checklist_templates,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.exists' => 'Tipe mesin belum terdaftar. Tambahkan dulu di Settings → Tipe Mesin.',
            'location.exists' => 'Lokasi belum terdaftar. Tambahkan dulu di Settings → Lokasi / Site.',
        ];
    }
}
