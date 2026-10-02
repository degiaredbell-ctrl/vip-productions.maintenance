<?php

namespace App\Http\Controllers;

use App\Enums\Period;
use App\Enums\PmDisplayStatus;
use App\Enums\SignatureStage;
use App\Models\PmRecord;
use App\Services\PeriodService;
use App\Services\SignatureChain;
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
            ->with(['machine', 'technician', 'signatures', 'items'])
            ->where('year', $year)
            ->withCount('items')
            ->when($period !== 'all' && $this->isValidPeriod($period, $year), fn ($q) => $q->where('period', $period))
            // Record yang paling lama menunggu didahulukan agar antrean tidak
            // menumpuk diam-diam di halaman yang sama.
            ->orderBy('updated_at')
            ->get()
            ->map(fn (PmRecord $record) => $this->mapRecord($record, $user));

        return Inertia::render('Approvals/Index', [
            'records' => $records,
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

        return [
            'id' => $record->id,
            'machine_id' => $record->machine_id,
            'code' => $record->machine?->code,
            'name' => $record->machine?->name,
            'location' => $record->machine?->location,
            'sub_category' => $record->machine?->sub_category,
            'period' => $record->period,
            'period_label' => Period::tryFrom($record->period)?->label() ?? $record->period,
            'year' => $record->year,
            'status' => $display->value,
            'status_label' => $display->label(),
            'status_detail' => $record->status->label(),
            'technician_name' => $record->technician?->name,
            'awaiting' => $awaiting?->value,
            'awaiting_label' => $awaiting?->shortLabel(),
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
