<?php

namespace App\Http\Requests;

use App\Enums\Period;
use App\Models\PmRecord;
use App\Services\PeriodService;
use App\Services\SignatureChain;
use App\Services\SignatureStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePmRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('pm.fill')) return false;

        // Menyimpan lewat form ini selalu ditutup dengan tanda tangan tahap
        // teknisi, jadi izin tanda tangan ikut diperiksa di sini. Kalau hanya
        // `pm.fill` yang dicek, form yang sengaja disembunyikan masih bisa
        // dipanggil langsung dan checklist tersimpan tanpa tanda tangan.
        if (! $user->can('pm.sign')) return false;

        // Record yang sudah berjalan di rantai approval terkunci: isinya yang
        // direview approver harus sama persis dengan yang ditandatangani.
        $existing = PmRecord::query()
            ->where('machine_id', $this->input('machine_id'))
            ->where('year', $this->input('year'))
            ->where('period', $this->input('period'))
            ->first();

        return $existing === null || $user->can('update', $existing);
    }

    /**
     * Mode "Data Saya": nama dokumen = nama akun login, dan tanda tangan boleh
     * diambil dari Profile. Nama dari client diabaikan supaya mode ini tidak
     * bisa dipakai menandatangani atas nama orang lain dengan tanda tangan
     * tersimpan milik akun.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isSelfMode() && $this->user() !== null) {
            $this->merge(['technician_name' => $this->user()->name]);
        }
    }

    public function isSelfMode(): bool
    {
        return $this->input('signer_mode') === 'self';
    }

    public function usesSavedSignature(): bool
    {
        return $this->isSelfMode() && $this->boolean('use_saved_signature');
    }

    /** Gambar baru disalin ke Profile hanya di mode "Data Saya". */
    public function savesSignatureToProfile(): bool
    {
        return $this->isSelfMode() && ! $this->usesSavedSignature() && $this->boolean('save_signature_to_profile');
    }

    public function rules(): array
    {
        return [
            'machine_id' => ['required', 'exists:assets,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:' . (date('Y') + 1)],
            'period' => ['required', Rule::enum(Period::class)],
            // Nama teknisi wajib diketik manual: nama ini yang jadi rujukan
            // dokumen, bukan nama akun yang squeez-in. Identitas akun disimpan
            // terpisah di technician_id untuk keperluan hak akses.
            'technician_name' => ['required', 'string', 'max:100'],
            // Teknisi menunjuk User PIC yang akan menandatangani tahap 2.
            // Daftar yang boleh dipilih diambil dari sumber yang sama dengan
            // dropdown di form, jadi request yang mengarang id user lain
            // (misalnya teknisi atau admin) ditolak di sini, bukan hanya
            // disembunyikan di tampilan.
            'pic_user_id' => [
                'required',
                Rule::in(SignatureChain::picCandidates()->pluck('id')->all()),
            ],
            // Data URL PNG dari canvas, bukan upload multipart, supaya tidak
            // butuh storage:link dan tidak ada file sementara di server.
            // Wajib kecuali memakai tanda tangan tersimpan (diperiksa di
            // withValidator karena bergantung pada signer_mode).
            'signature' => ['nullable', 'string', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', 'max:' . SignatureStorage::MAX_CHARS],
            'signer_mode' => ['sometimes', Rule::in(['self', 'manual'])],
            'use_saved_signature' => ['sometimes', 'boolean'],
            'save_signature_to_profile' => ['sometimes', 'boolean'],
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
            // Detail bagian yang diganti. Panjang dibatasi 255 mengikuti kolom
            // `replace_note`; kewajiban mengisinya saat "Perlu Pergantian"
            // dicentang diperiksa per-item di withValidator() di bawah.
            'items.*.replace_note' => ['nullable', 'string', 'max:255'],
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

            if ($this->boolean('use_saved_signature') && ! $this->isSelfMode()) {
                $validator->errors()->add(
                    'use_saved_signature',
                    'Tanda tangan tersimpan hanya bisa dipakai dengan opsi "Data Saya".'
                );
            } elseif ($this->usesSavedSignature() && $this->user()?->signature_path === null) {
                $validator->errors()->add(
                    'use_saved_signature',
                    'Anda belum menyimpan tanda tangan di halaman Profile. Gambar tanda tangan di form ini.'
                );
            } elseif (! $this->usesSavedSignature() && ! filled($this->input('signature'))) {
                $validator->errors()->add('signature', 'Tanda tangan wajib digambar.');
            }

            // Ketika teknisi mencentang aksi "Perlu Pergantian", wajib menjelaskan
            // bagian apa yang diganti. Tanpa ini riwayat PM hanya menampilkan tanda
            // centang tanpa keterangan komponen yang benar-benar diganti.
            foreach ((array) $this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $replace = filter_var($item['act_replace'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $note = trim((string) ($item['replace_note'] ?? ''));

                if ($replace && $note === '') {
                    $validator->errors()->add(
                        "items.{$index}.replace_note",
                        'Detail bagian yang diganti wajib diisi ketika aksi "Perlu Pergantian" dipilih.',
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.*.actual.required' => 'Nilai aktual wajib diisi.',
            'items.required' => 'Minimal satu item checklist.',
            'technician_name.required' => 'Nama teknisi wajib diisi.',
            'pic_user_id.required' => 'Pilih User PIC yang akan menyetujui berikutnya.',
            'pic_user_id.in' => 'User PIC yang dipilih tidak tersedia sebagai penyetuju.',
            'signature.required' => 'Tanda tangan wajib digambar.',
            'signature.regex' => 'Tanda tangan tidak valid. Silakan gambar ulang.',
        ];
    }
}
