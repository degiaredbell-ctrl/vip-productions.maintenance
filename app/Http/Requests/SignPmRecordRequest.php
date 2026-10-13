<?php

namespace App\Http\Requests;

use App\Enums\SignatureStage;
use App\Services\SignatureStorage;
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
            // Persetujuan punya dua sumber tanda tangan: PNG yang digambar
            // baru dari canvas, atau tanda tangan tersimpan di profil akun
            // (`use_saved_signature`). Minimal salah satu dipilih; dua-duanya
            // dijamin lewat withValidator() karena rules() tidak tahu tahap
            // mana yang aktif.
            'signature' => ['nullable', 'string', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', 'max:' . SignatureStorage::MAX_CHARS],
            'use_saved_signature' => ['sometimes', 'boolean'],
            // Saat menggambar baru, user bisa sekaligus menyimpan hasilnya ke
            // profil agar dipakai lagi di persetujuan berikutnya.
            'save_signature_to_profile' => ['sometimes', 'boolean'],
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
     * Pemeriksaan tambahan yang tidak bisa ditutup aturan biasa.
     *
     * 1. Minimal satu sumber tanda tangan: gambar baru ATAU tanda tangan
     *    tersimpan di profil. Tanpa keduanya, tidak ada approve.
     * 2. Tanda tangan tersimpan hanya untuk tahap persetujuan (PIC/Atasan),
     *    bukan tahap teknisi yang menandatangani pekerjaan orang lain.
     * 3. `use_saved_signature` harus didukung data di profil: kalau user belum
     *    pernah menyimpan, endpoint menolak dengan pesan yang jelas.
     * 4. Nama wajib diisi hanya pada tahap teknisi. Tahap PIC/Atasan memakai
     *    nama akun login, jadi tidak boleh dipasang `required` di rules() —
     *    rules() tidak tahu tahap mana yang sedang aktif.
     * 5. Checklist harus sudah terisi sebelum tahap teknisi diteken.
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

            $useSaved = $this->boolean('use_saved_signature');
            $drawn = $this->input('signature');

            if ($useSaved && $awaiting === SignatureStage::Technician) {
                $validator->errors()->add(
                    'use_saved_signature',
                    'Tanda tangan tersimpan tidak dipakai untuk tahap teknisi. Gambar tanda tangan dulu.'
                );
            }

            if ($useSaved && $this->user()?->signature_path === null) {
                $validator->errors()->add(
                    'use_saved_signature',
                    'Anda belum menyimpan tanda tangan di halaman Profile. Simpan dulu, atau gambar di sini.'
                );
            }

            if (! $useSaved && (! is_string($drawn) || $drawn === '')) {
                $validator->errors()->add(
                    'signature',
                    'Tanda tangan wajib digambar atau gunakan tanda tangan tersimpan.'
                );
            }

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
            'signature.regex' => 'Tanda tangan tidak valid. Silakan gambar ulang.',
            'signature.max' => 'Ukuran gambar tanda tangan terlalu besar.',
            'use_saved_signature.boolean' => 'Parameter tanda tangan tersimpan tidak valid.',
            'save_signature_to_profile.boolean' => 'Parameter simpan ke profil tidak valid.',
            'signer_name.required' => 'Nama penanda tangan wajib diisi.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}