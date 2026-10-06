<?php

namespace App\Http\Requests;

use App\Enums\Period;
use App\Models\PmRecord;
use App\Services\PeriodService;
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

    public function rules(): array
    {
        return [
            'machine_id' => ['required', 'exists:machines,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:' . (date('Y') + 1)],
            'period' => ['required', Rule::enum(Period::class)],
            // Nama teknisi wajib diketik manual: nama ini yang jadi rujukan
            // dokumen, bukan nama akun yang squeez-in. Identitas akun disimpan
            // terpisah di technician_id untuk keperluan hak akses.
            'technician_name' => ['required', 'string', 'max:100'],
            // Data URL PNG dari canvas, bukan upload multipart, supaya tidak
            // butuh storage:link dan tidak ada file sementara di server.
            'signature' => ['required', 'string', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/'],
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
            'technician_name.required' => 'Nama teknisi wajib diisi.',
            'signature.required' => 'Tanda tangan wajib digambar.',
            'signature.regex' => 'Tanda tangan tidak valid. Silakan gambar ulang.',
        ];
    }
}
