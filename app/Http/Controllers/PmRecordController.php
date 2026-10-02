<?php

namespace App\Http\Controllers;

use App\Actions\Pm\RejectPmRecord;
use App\Actions\Pm\SignPmRecord;
use App\Actions\Pm\SubmitPmRecord;
use App\Enums\Period;
use App\Enums\SignatureStage;
use App\Http\Requests\RejectPmRecordRequest;
use App\Http\Requests\SignPmRecordRequest;
use App\Http\Requests\StorePmRecordRequest;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Services\PeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PmRecordController extends Controller
{
    public function create(Request $request, Machine $machine): Response
    {
        $period = Period::tryFrom($request->query('period', Period::current()->value)) ?? Period::current();
        $year = (int) $request->query('year', now()->year);

        $existing = PmRecord::with(['items', 'signatures'])
            ->where('machine_id', $machine->id)
            ->where('year', $year)
            ->where('period', $period->value)
            ->first();

        $template = $machine->template ?? $machine->type ? \App\Models\ChecklistTemplate::where('machine_type', $machine->type)->where('is_default', true)->first() : null;

        $items = $existing ? $existing->items->map(function ($item) {
            return [
                'item_name' => $item->item_name,
                'category' => $item->category,
                'spec' => $item->spec,
                'actual' => $item->actual,
                'act_clean' => $item->act_clean,
                'act_repair' => $item->act_repair,
                'act_lubricate' => $item->act_lubricate,
                'act_replace' => $item->act_replace,
                'final_condition' => $item->final_condition,
                'parts_replaced' => $item->parts_replaced,
            ];
        })->toArray() : ($template ? $template->items->map(function ($item) {
            return [
                'item_name' => $item->name,
                'category' => $item->category,
                'spec' => $item->spec,
                'actual' => '',
                'act_clean' => false,
                'act_repair' => false,
                'act_lubricate' => false,
                'act_replace' => false,
                'final_condition' => '',
                'parts_replaced' => 0,
            ];
        })->toArray() : []);

        // Filter yang sedang aktif di Beranda ikut diteruskan, supaya tombol
        // "Kembali" dan redirect setelah simpan tidak membuat user kehilangan
        // filter periode/area/status/pencarian yang sedang dipilih.
        $dashboardUrl = route('dashboard', array_filter([
            'period' => $period->value,
            'year' => $year,
            'sub' => $request->query('sub'),
            'status' => $request->query('status'),
            'q' => $request->query('q'),
        ], fn ($value) => $value !== null && $value !== '' && $value !== 'all'));

        // Disimpan lewat session, bukan diambil dari input form, supaya URL
        // tujuan redirect tidak bisa dipakai untuk open redirect.
        $request->session()->put('pm_dashboard_url', $dashboardUrl);

        $user = $request->user();

        return Inertia::render('Pm/Form', [
            'machine' => [
                'id' => $machine->id,
                'code' => $machine->code,
                'name' => $machine->name,
                'location' => $machine->location,
                'category' => $machine->category,
                'sub_category' => $machine->sub_category,
                'type' => $machine->type,
                'week_group' => $machine->week_group,
            ],
            'items' => $items,
            'period' => $period->value,
            'year' => $year,
            'dashboardUrl' => $dashboardUrl,
            'existing' => $existing ? [
                'id' => $existing->id,
                'status' => $existing->status->value,
                'status_label' => $existing->status->label(),
                'revision_count' => $existing->revision_count,
                'general_note' => $existing->general_note,
            ] : null,
            'isFuturePeriod' => PeriodService::isFuturePeriod($period, $year),
            'canFill' => $user->can('pm.fill') && (! $existing || $user->can('update', $existing)),
            'chain' => $this->chainProps($existing, $user),
        ]);
    }

    public function store(StorePmRecordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $record = app(SubmitPmRecord::class)->handle($data);

        // Kembali ke Beranda dengan filter yang tadi sedang aktif.
        return redirect()
            ->to($request->session()->pull('pm_dashboard_url', route('dashboard')))
            ->with('success', $record->wasRecentlyCreated
                ? 'Checklist PM berhasil disimpan. Lanjutkan dengan tanda tangan.'
                : 'Checklist PM berhasil disimpan. Tanda tangan sebelumnya dibatalkan karena checklist berubah.');
    }

    /**
     * Satu endpoint untuk ketiga tahap. Tahap mana yang aktif ditentukan dari
     * status record, bukan dari parameter, jadi rantai tidak bisa dilompati.
     */
    public function sign(SignPmRecordRequest $request, PmRecord $record): RedirectResponse
    {
        $stage = $record->status->awaiting();

        app(SignPmRecord::class)->handle($record, $request->validated('signature'), $request->validated('note'));

        return redirect()->back()->with('success', match ($stage) {
            SignatureStage::Technician => 'Tanda tangan dicatat. Menunggu persetujuan User PIC.',
            SignatureStage::Pic => 'Persetujuan User PIC dicatat. Menunggu persetujuan Atasan.',
            SignatureStage::Supervisor => 'PM disetujui penuh dan berstatus Selesai.',
            default => 'Tanda tangan dicatat.',
        });
    }

    public function reject(RejectPmRecordRequest $request, PmRecord $record): RedirectResponse
    {
        app(RejectPmRecord::class)->handle($record, $request->validated('note'));

        return redirect()->back()->with('success', 'PM ditolak dan dikembalikan ke teknisi untuk revisi.');
    }

    /**
     * Data rantai persetujuan untuk stepper dan panel aksi di form PM.
     */
    private function chainProps(?PmRecord $record, $user): array
    {
        $status = $record?->status;

        return [
            'stages' => collect(SignatureStage::ordered())->map(function (SignatureStage $stage) use ($record) {
                $signature = $record?->signatureFor($stage);

                return [
                    'value' => $stage->value,
                    'label' => $stage->label(),
                    'short_label' => $stage->shortLabel(),
                    'signed' => $signature !== null,
                    'signed_by_name' => $signature?->signed_by_name,
                    'signed_at' => $signature?->signed_at?->translatedFormat('d M Y H:i'),
                    'note' => $signature?->note,
                    'image_url' => $signature?->imageUrl(),
                ];
            })->values()->all(),
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'awaiting' => $status?->awaiting()?->value,
            'awaiting_label' => $status?->awaiting()?->label(),
            'canSign' => $record !== null && $user->can('sign', $record),
            'canReject' => $record !== null && $user->can('reject', $record),
        ];
    }
}
