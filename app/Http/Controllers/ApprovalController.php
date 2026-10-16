<?php

namespace App\Http\Controllers;

use App\Enums\MachineType;
use App\Enums\Period;
use App\Enums\PmDisplayStatus;
use App\Enums\PmStatus;
use App\Enums\SignatureStage;
use App\Models\PmRecord;
use App\Services\PeriodService;
use App\Services\SignatureChain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Layar "Persetujuan": daftar PM yang menunggu tanda tangan/approval user saat
 * ini. aprobarsviable lewat form PM mesin tersebut.
 */
class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $years = PeriodService::availableYears();
        $year = (int) $request->query('year', now()->year);

        if (!in_array($year, $years, true)) {
            $year = now()->year;
        }

        $period = (string) $request->query('period', 'all');
        $stages = SignatureChain::stagesFor($user);

        $records = SignatureChain::pendingQuery($user)
            ->with(['machine', 'technician', 'picUser', 'signatures', 'items'])
            ->where('year', $year)
            ->withCount('items')
            ->when($period !== 'all' && $this->isValidPeriod($period, $year), fn ($q) => $q->where('period', $period))
            // Record yang paling lama menunggu didahulukan agar antrean tidak
            // menumpuk diam-diam di halaman yang sama.
            ->orderBy('updated_at')
            ->get()
            ->map(fn (PmRecord $record) => $this->mapRecord($record, $user));

        $mySubmissions = $this->mySubmissionsQuery($user)
            ->with(['machine', 'picUser', 'signatures'])
            ->where('year', $year)
            ->when($period !== 'all' && $this->isValidPeriod($period, $year), fn ($q) => $q->where('period', $period))
            ->orderByDesc('updated_at')
            ->get()
            // Yang masih berjalan di rantai didahulukan, lalu yang perlu
            // revisi, terakhir yang sudah selesai.
            ->sortBy(fn (PmRecord $record) => match ($record->status) {
                PmStatus::Submitted, PmStatus::PicApproved => 0,
                PmStatus::Rejected => 1,
                default => 2,
            })
            ->values()
            ->map(fn (PmRecord $record) => $this->mapSubmission($record));

        return Inertia::render('Approvals/Index', [
            'records' => $records,
            // PM yang dibuat (diisi & ditandatangani) user ini, supaya pembuat
            // bisa memantau sudah sampai tahap User PIC atau Atasan.
            'mySubmissions' => $mySubmissions,
            'canSubmit' => $user->can(SignatureStage::Technician->permission()),
            'years' => $years,
            'year' => $year,
            'period' => $period,
            'periods' => collect(Period::cases())
                ->filter(fn (Period $p) => ! $p->isFuture($year))
                ->map(fn (Period $p) => ['value' => $p->value, 'label' => $p->label()])
                ->values()
                ->all(),
            // Tahap milik user ini, supaya halaman bisa menjelaskan apa yang
            // ditunggu darinya alih-alih menampilkan daftar kosong tanpa alasan.
            'myStages' => collect($stages)
                ->map(fn (SignatureStage $stage) => [
                    'value' => $stage->value,
                    'label' => $stage->label(),
                    'short_label' => $stage->shortLabel(),
                ])
                ->values()
                ->all(),
            'hasStages' => $stages !== [],
        ]);
    }

    /**
     * PM milik user ini yang sudah dikirim ke rantai persetujuan. Draft tidak
     * ikut karena belum ada yang perlu dipantau.
     */
    private function mySubmissionsQuery($user): Builder
    {
        return PmRecord::query()
            ->where('technician_id', $user->id)
            ->whereIn('status', [
                PmStatus::Submitted->value,
                PmStatus::PicApproved->value,
                PmStatus::Rejected->value,
                PmStatus::Approved->value,
            ]);
    }

    private function mapSubmission(PmRecord $record): array
    {
        $display = PmDisplayStatus::fromPmStatus($record->status);
        $awaiting = $record->status->awaiting();
        $isUtility = $record->machine?->type === MachineType::Utility->value;
        $isRejected = $record->status === PmStatus::Rejected;

        $rejection = $isRejected ? $record->lastRejection() : null;
        $rejectionChanges = $rejection?->getAttribute('changes');
        $rejectedStage = is_array($rejectionChanges) ? ($rejectionChanges['stage'] ?? null) : null;

        return [
            'id' => $record->id,
            'machine_id' => $record->machine_id,
            'code' => $record->machine?->code,
            'name' => $record->machine?->name,
            'location' => $record->machine?->location,
            'sub_category' => $record->machine?->sub_category,
            'category' => $isUtility ? 'utility' : 'machine',
            'category_label' => $isUtility ? 'Utility' : 'Mesin',
            'period' => $record->period,
            'period_label' => Period::tryFrom($record->period)?->label() ?? $record->period,
            'year' => $record->year,
            'status' => $display->value,
            'status_label' => $display->label(),
            'status_detail' => $record->status->label(),
            'is_rejected' => $isRejected,
            'is_final' => $record->status->isFinal(),
            // Saat ditolak semua tanda tangan dibuang dan PM kembali ke
            // teknisi, jadi "menunggu" di sini berarti menunggu revisi dari
            // pembuatnya sendiri.
            'awaiting' => $isRejected ? null : $awaiting?->value,
            'awaiting_label' => $isRejected ? null : $awaiting?->shortLabel(),
            'assigned_pic' => $record->picUser?->name,
            'stages' => collect(SignatureStage::ordered())->map(function (SignatureStage $stage) use ($record, $isRejected, $rejectedStage) {
                $signature = $record->signatureFor($stage);

                return [
                    'value' => $stage->value,
                    'short_label' => $stage->shortLabel(),
                    'order' => $stage->order(),
                    'signed' => $signature !== null,
                    'signed_by_name' => $signature?->signed_by_name,
                    'signed_at' => $signature?->signed_at?->translatedFormat('d M Y H:i'),
                    'rejected' => $isRejected && $rejectedStage === $stage->value,
                ];
            })->values()->all(),
            'rejection' => $rejection ? [
                'by' => $rejection->user?->name,
                'at' => $rejection->created_at?->translatedFormat('d M Y H:i'),
                'reason' => is_array($rejectionChanges) ? ($rejectionChanges['reason'] ?? null) : null,
            ] : null,
            'revision_count' => $record->revision_count,
            'updated_at' => $record->updated_at?->translatedFormat('d M Y H:i'),
        ];
    }

    /**
     * Periode terkunci tidak boleh difilter di halaman ini, sama seperti di
     * Beranda: tidak ada yang bisa ditandatangani untuk periode yang belum tiba.
     */
    private function isValidPeriod(string $period, int $year): bool
    {
        $case = Period::tryFrom($period);

        return $case !== null && ! $case->isFuture($year);
    }

    private function mapRecord(PmRecord $record, $user): array
    {
        $display = PmDisplayStatus::fromPmStatus($record->status);
        $awaiting = $record->status->awaiting();

        // Kategori baris: unit utility dikelola terpisah dari mesin produksi,
        // jadi approver perlu tahu ini PM unit mana. Dikirim sebagai pasangan
        // kunci+label seperti status/period supaya pewarnaan di UI memakai
        // kunci yang stabil, bukan teks tampilannya.
        $isUtility = $record->machine?->type === MachineType::Utility->value;

        return [
            'id' => $record->id,
            'machine_id' => $record->machine_id,
            'code' => $record->machine?->code,
            'name' => $record->machine?->name,
            'location' => $record->machine?->location,
            'sub_category' => $record->machine?->sub_category,
            'category' => $isUtility ? 'utility' : 'machine',
            'category_label' => $isUtility ? 'Utility' : 'Mesin',
            'period' => $record->period,
            'period_label' => Period::tryFrom($record->period)?->label() ?? $record->period,
            'year' => $record->year,
            'status' => $display->value,
            'status_label' => $display->label(),
            'status_detail' => $record->status->label(),
            'technician_name' => $record->technicianName(),
            // User PIC yang ditunjuk teknisi, ditampilkan supaya jelas
            // baris ini menunggu siapa. Pembatasan siapa yang boleh
            // menyetujui sudah ditegakkan di SignatureChain, jadi User PIC lain
            // tidak menerima record ini sama sekali.
            'assigned_pic' => $record->picUser ? [
                'id' => $record->picUser->id,
                'name' => $record->picUser->name,
            ] : null,
            'awaiting' => $awaiting?->value,
            'awaiting_label' => $awaiting?->shortLabel(),
            // Bentuk tahap untuk panel tanda tangan inline. Label tahap dikirim
            // utuh supaya nama mandatory di tiap kartu menyebut orang yang
            // seharusnya menandatangani, bukan "Nama Penanda Tangan" generik.
            'awaiting_stage' => $awaiting === null ? null : [
                'value' => $awaiting->value,
                'label' => $awaiting->label(),
                'short_label' => $awaiting->shortLabel(),
            ],
            'revision_count' => $record->revision_count,
            'updated_at' => $record->updated_at?->translatedFormat('d M Y H:i'),
            'filled' => $record->items_count,
            'filled_percent' => $this->filledPercent($record),
            'can_sign' => SignatureChain::canSign($user, $record),
            'can_reject' => SignatureChain::canReject($user, $record),
        ];
    }

    /**
     * Berapa persen checklist yang sudah terisi nilai aktual, supaya user bisa
     * melihat PM yang memang siap ditandatangani.
     */
    private function filledPercent(PmRecord $record): int
    {
        $items = $record->items;
        $total = $items->count();

        if ($total === 0) {
            return 0;
        }

        return (int) round($items->filter(fn ($item) => filled($item->actual))->count() / $total * 100);
    }
}
