<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectPmRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record !== null
            && $this->user()?->can('reject', $record) === true;
    }

    public function rules(): array
    {
        return [
            // Alasan wajib supaya teknisi tahu apa yang harus diperbaiki,
            // bukan sekadar melihat status "Perlu Revisi".
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Alasan penolakan wajib diisi.',
            'note.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
