<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignPmRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('record');

        return $record !== null
            && $this->user()?->can('sign', $record) === true;
    }

    public function rules(): array
    {
        return [
            // Data URL PNG dari canvas, bukan upload multipart, supaya tidak
            // butuh storage:link dan tidak ada file sementara di server.
            'signature' => ['required', 'string', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'signature.required' => 'Tanda tangan wajib digambar.',
            'signature.regex' => 'Tanda tangan tidak valid. Silakan gambar ulang.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
