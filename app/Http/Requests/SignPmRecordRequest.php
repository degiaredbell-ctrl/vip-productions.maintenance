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
            // Tahap persetujuan (PIC/Atasan) tidak perlu nama dari client:
            // approver sudah login sebagai dirinya sendiri dan backend memakai
            // nama akun itu. Field ini tetap diterima supaya request lama tidak
            // ditolak, tapi required hanya untuk tahap teknisi (lihat
            // withValidator) yang menandatangani pekerjaannya sendiri.
            'signer_name' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Dua pemeriksaan tambahan yang tidak bisa ditutup aturan biasa.
     *
     * 1. Nama wajib diisi hanya pada tahap teknisi. Tahap PIC/Atasan memakai
     *    nama akun login, jadi tidak boleh dipasang `required` di rules() —
     *    rules() tidak tahu tahap mana yang sedang aktif.
     * 2. Checklist harus sudah terisi sebelum tahap teknisi diteken.
     *    Aturan `signature` saja tidak cukup: endpoint ini juga bisa dipanggil
     *    langsung dari luar form PM, di mana PM miliknya sendiri masih
     *    berstatus draft. Tanpa pemeriksaan ini PM kosong bisa diteruskan ke PIC
     *    hanya dengan satu tanda tangan.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $record = $this->route('record');

            if (! $record instanceof PmRecord) {
                return;
            }

            $awaiting = $record->status->awaiting();

            if ($awaiting === SignatureStage::Technician) {
                $name = $this->input('signer_name');

                if (! is_string($name) || trim($name) === '') {
                    $validator->errors()->add('signer_name', 'Nama teknisi wajib diisi.');
                }

                $record->loadMissing('items');

                if (! $record->isChecklistComplete()) {
                    $validator->errors()->add(
                        'signature',
                        'Checklist PM belum lengkap: masih ada nilai Aktual yang kosong.'
                    );
                }

                return;
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
