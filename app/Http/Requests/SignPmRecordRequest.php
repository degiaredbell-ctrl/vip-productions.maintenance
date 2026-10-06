<?php

namespace App\Http\Requests;

use App\Enums\SignatureStage;
use App\Models\PmRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            // Nama penanda tangan wajib diketik manual di tiap tahap, bukan
            // diambil dari akun login, karena nama di dokumen harus menyatakan
            // siapa yang benar-benar menandatangani.
            'signer_name' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Checklist harus sudah terisi sebelum tahap teknisi diteken.
     *
     * Aturan `signature` saja tidak cukup: endpoint ini juga bisa dipanggil
     * langsung dari daftar Persetujuan, di mana PM miliknya sendiri masih
     * berstatus draft. Tanpa pemeriksaan ini PM kosong bisa diteruskan ke PIC
     * hanya dengan satu tanda tangan.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $record = $this->route('record');

            if (! $record instanceof PmRecord) {
                return;
            }

            if ($record->status->awaiting() !== SignatureStage::Technician) {
                return;
            }

            $record->loadMissing('items');

            if (! $record->isChecklistComplete()) {
                $validator->errors()->add(
                    'signature',
                    'Checklist PM belum lengkap: masih ada nilai Aktual yang kosong.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'signature.required' => 'Tanda tangan wajib digambar.',
            'signature.regex' => 'Tanda tangan tidak valid. Silakan gambar ulang.',
            'signer_name.required' => 'Nama penanda tangan wajib diisi.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
