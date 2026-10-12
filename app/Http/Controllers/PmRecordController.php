<?php

namespace App\Http\Controllers;

use App\Actions\Pm\RejectPmRecord;
use App\Actions\Pm\SignPmRecord;
use App\Actions\Pm\SubmitPmRecord;
use App\Enums\MachineType;
use App\Enums\Period;
use App\Enums\SignatureStage;
use App\Http\Requests\RejectPmRecordRequest;
use App\Http\Requests\SignPmRecordRequest;
use App\Http\Requests\StorePmRecordRequest;
use App\Models\ChecklistTemplate;
use App\Models\Machine;
use App\Models\PmRecord;
use App\Services\PeriodService;
use App\Services\SignatureChain;
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

        $existing = PmRecord::with(['items', 'signatures', 'picUser'])
            ->where('machine_id', $machine->id)
            ->where('year', $year)
            ->where('period', $period->value)
            ->first();

        $template = $machine->template ?? ($machine->type ? ChecklistTemplate::where('machine_type', $machine->type)->where('is_default', true)->first() : null);

        // Sumber item form PM: snapshot record yang sudah ada lebih dulu, lalu
        // master Komponen milik unit ini. Template lama hanya dipakai sebagai
        // cadangan bila unit belum punya komponen sama sekali (data yang belum
        // ikut termigrasi), supaya form tidak kosong tanpa penjelasan.
        if ($existing) {
            $items = $existing->items->map(function ($item) {
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
            })->toArray();
        } elseif ($machine->components->isNotEmpty()) {
            $items = $machine->components->map(function ($component) {
                return [
                    'item_name' => $component->name,
                    'category' => $component->category,
                    'spec' => $component->spec,
                    'actual' => '',
                    'act_clean' => false,
                    'act_repair' => false,
                    'act_lubricate' => false,
                    'act_replace' => false,
                    'final_condition' => '',
                    'parts_replaced' => 0,
                ];
            })->toArray();
        } else {
            $items = $template ? $template->items->map(function ($item) {
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
            })->toArray() : [];
        }

        // Filter yang sedang aktif di Beranda ikut diteruskan, supaya tombol
        // "Kembali" dan redirect setelah simpan tidak membuat user kehilangan
        // filter periode/area/status/pencarian yang sedang dipilih. Untuk unit
        // utility, kembali ke halaman Utility (unit itu tidak ada di Beranda).
        // Disimpan lewat session, bukan diambil dari input form, supaya URL
        // tujuan redirect tidak bisa dipakai untuk open redirect.
        if ($machine->type === MachineType::Utility->value) {
            $return = $request->query('return');
            if ($return === 'utility') {
                $dashboardUrl = route('form-maintenance.utility', array_filter([
                    'period' => $period->value,
                    'year' => $year,
                    'sub' => $request->query('sub'),
                    'status' => $request->query('status'),
                    'q' => $request->query('q'),
                ], fn ($value) => $value !== null && $value !== '' && $value !== 'all'));
            } else {
                $dashboardUrl = route('utility.index');
            }
            $request->session()->put('pm_dashboard_url', $dashboardUrl);
        } else {
            $dashboardUrl = route('dashboard', array_filter([
                'period' => $period->value,
                'year' => $year,
                'sub' => $request->query('sub'),
                'status' => $request->query('status'),
                'q' => $request->query('q'),
            ], fn ($value) => $value !== null && $value !== '' && $value !== 'all'));

            $request->session()->put('pm_dashboard_url', $dashboardUrl);
        }

        $user = $request->user();

        // Penolakan terakhir dibaca sekali di sini supaya form tidak perlu
        // melakukan query audit log sendiri.
        $rejection = $this->lastRejectionProps($existing);

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
                // Nama teknisi ikut diteruskan supaya revisi tidak memaksa
                // teknisi mengetik ulang namanya dari nol.
                'technician_name' => $existing->technician_name,
                // User PIC yang sudah ditunjuk pada simpan sebelumnya, supaya
                // revisi tidak diam-diam mengganti orang yang menunggu. Teknisi
                // tetap bebas memilih PIC lain di kolom ini.
                'pic_user_id' => $existing->pic_user_id,
                // Catatan penolakan terakhir. Tanpa ini teknisi hanya melihat
                // status "Perlu Revisi" tanpa tahu apa yang harus diperbaiki.
                'rejected_by' => $rejection['by'],
                'rejected_at' => $rejection['at'],
                'reject_reason' => $rejection['reason'],
                // Stempel waktu dokumen, hanya untuk record yang sudah pernah
                // submit (`submitted_at`). Draft yang belum pernah dikirim
                // dikirim sebagai null, dan form tidak merender apapun —
                // "dibuat" tanpa "pernah dikirim" hanya menambah kebisingan di
                // form yang isinya masih mentah.
                'created_at' => $existing->submitted_at
                    ? $existing->created_at?->translatedFormat('d M Y H:i')
                    : null,
                'updated_at' => $existing->submitted_at
                    ? $existing->updated_at?->translatedFormat('d M Y H:i')
                    : null,
            ] : null,
            'isFuturePeriod' => PeriodService::isFuturePeriod($period, $year),
            'canFill' => $user->can('pm.fill') && (! $existing || $user->can('update', $existing)),
            // Menyimpan lewat form ini selalu sekaligus menandatangani tahap
            // teknisi, jadi izinnya ikut dikirim supaya tombol submit bisa
            // dinonaktifkan dari awal bila memang tidak boleh.
            'canSignTechnician' => $user->can('pm.sign'),
            // User PIC yang bisa dipilih teknisi untuk tahap 2. Daftarnya diambil
            // dari SignatureChain, sama dengan sumber yang dipakai untuk
            // memvalidasi request, supaya pilihan di form tidak pernah
            // menampilkan orang yang lalu ditolak backend.
            'picCandidates' => SignatureChain::picCandidates()
                ->get(['id', 'name'])
                ->map(fn ($pic) => [
                    'id' => $pic->id,
                    'name' => $pic->name,
                ])
                ->values()
                ->all(),
            'chain' => $this->chainProps($existing, $user),
        ]);
    }

    public function store(StorePmRecordRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Satu tombol submit menutup dua hal sekaligus: checklist tersimpan
        // dan tahap teknisi sudah ditandatangani. Kalau signing gagal,
        // checklist tetap dikembalikan ke Beranda dengan status draft supaya
        // tidak ada data yang hilang tanpa jejak.
        $record = app(SubmitPmRecord::class)->handle($data);

        try {
            app(SignPmRecord::class)->handle(
                $record,
                $data['signature'],
                $data['note'] ?? null,
                $data['technician_name'],
            );
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->to($request->session()->pull('pm_dashboard_url', route('dashboard')))
                ->with('error', 'Checklist PM tersimpan, tetapi tanda tangan gagal disimpan. Buka kembali PM untuk menandatangani.');
        }

        return redirect()
            ->to($request->session()->pull('pm_dashboard_url', route('dashboard')))
            ->with('success', 'Checklist PM tersimpan dan sudah ditandatangani. Menunggu persetujuan User PIC.');
    }

    /**
     * Satu endpoint untuk ketiga tahap. Tahap mana yang aktif ditentukan dari
     * status record, bukan dari parameter, jadi rantai tidak bisa dilompati.
     */
    public function sign(SignPmRecordRequest $request, PmRecord $record): RedirectResponse
    {
        $stage = $record->status->awaiting();

        app(SignPmRecord::class)->handle(
            $record,
            $request->validated('signature'),
            $request->validated('note'),
            $request->validated('signer_name'),
        );

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
     * Ringkasan penolakan terakhir untuk ditampilkan di form revisi.
     *
     * Hanya diisi ketika record benar-benar ditolak; record yang belum pernah
     * ditolak atau sudah selesai tidak menampilkan apa pun.
     *
     * @return array{by: ?string, at: ?string, reason: ?string}
     */
    private function lastRejectionProps(?PmRecord $record): array
    {
        $log = $record?->lastRejection();

        if ($log === null) {
            return ['by' => null, 'at' => null, 'reason' => null];
        }

        $changes = $log->getAttribute('changes');
        $reason = is_array($changes) ? ($changes['reason'] ?? null) : null;

        return [
            'by' => $log->user?->name,
            'at' => $log->created_at?->translatedFormat('d M Y H:i'),
            'reason' => is_string($reason) && $reason !== '' ? $reason : null,
        ];
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
            // Siapa yang ditunjuk teknisi untuk tahap User PIC, supaya stepper
            // bisa menyebutkan orangnya, bukan hanya nama tahapnya.
            'assigned_pic' => $record?->picUser ? [
                'id' => $record->picUser->id,
                'name' => $record->picUser->name,
            ] : null,
            'canSign' => $record !== null && $user->can('sign', $record),
            'canReject' => $record !== null && $user->can('reject', $record),
            // Timeline riwayat dokumen (ascending: terlama di atas)
            'timeline' => $this->buildTimeline($record),
        ];
    }

    /**
     * Membangun timeline riwayat dokumen untuk form PM.
     *
     * @return array{time: string, label: string, detail?: string, type: string}[]
     */
    private function buildTimeline(?PmRecord $record): array
    {
        if (!$record) return [];

        $events = [];

        // 1. Dokument dibuat (saat pertama submit)
        if ($record->submitted_at) {
            $events[] = [
                'time' => $record->created_at?->format('d/m/Y H:i'),
                'label' => 'Dokumen dibuat',
                'detail' => 'Checklist dikirim untuk persetujuan',
                'type' => 'created',
            ];
        }

        // 2. Semua penolakan (ascending)
        foreach ($record->allRejections() as $rejection) {
            $changes = $rejection->getAttribute('changes');
            $reason = is_array($changes) ? ($changes['reason'] ?? null) : null;

            $events[] = [
                'time' => $rejection->created_at?->format('d/m/Y H:i'),
                'label' => 'Ditolak',
                'detail' => $reason ? "Alasan: {$reason}" : 'Tidak ada alasan',
                'type' => 'rejected',
                'by' => $rejection->user?->name,
            ];
        }

        // 3. Semua tanda tangan (ascending)
        foreach ($record->signatures->sortBy('signed_at') as $sig) {
            $stageLabel = match ($sig->stage->value) {
                'technician' => 'Teknisi',
                'pic' => 'User PIC',
                'supervisor' => 'Atasan',
                default => $sig->stage->label(),
            };

            $events[] = [
                'time' => $sig->signed_at?->format('d/m/Y H:i'),
                'label' => "Ditandatangani oleh {$stageLabel}",
                'detail' => $sig->signed_by_name ?? 'Tidak diketahui',
                'type' => 'signed',
                'note' => $sig->note,
            ];
        }

        // 4. Terakhir diubah (updated_at)
        if ($record->updated_at) {
            $events[] = [
                'time' => $record->updated_at?->format('d/m/Y H:i'),
                'label' => 'Terakhir diperbarui',
                'detail' => 'Data checklist atau status berubah',
                'type' => 'updated',
            ];
        }

        // Urutkan ascending berdasarkan waktu
        usort($events, function ($a, $b) {
            return strtotime(str_replace('/', '-', $a['time'])) <=> strtotime(str_replace('/', '-', $b['time']));
        });

        return $events;
    }

    /**
     * Admin: daftar semua PM Record dengan filter dan pencarian.
     */
    public function adminIndex(Request $request): Response
    {
        $query = PmRecord::with(['machine', 'technician', 'picUser', 'signatures', 'items']);

        // Filter: pencarian
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('technician_name', 'like', "%{$search}%")
                  ->orWhere('general_note', 'like', "%{$search}%")
                  ->orWhereHas('machine', function ($mq) use ($search) {
                      $mq->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                  });
            });
        }

        // Filter: status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter: periode
        if ($period = $request->query('period')) {
            $query->where('period', $period);
        }

        // Filter: tahun
        if ($year = $request->query('year')) {
            $query->where('year', $year);
        }

        // Filter: tipe mesin (mesin/utility)
        if ($type = $request->query('type')) {
            $query->whereHas('machine', function ($mq) use ($type) {
                $mq->where('type', $type);
            });
        }

        $records = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        // Data untuk filter dropdown
        $years = PmRecord::select('year')->distinct()->orderByDesc('year')->pluck('year');
        $periods = PmRecord::select('period')->distinct()->orderBy('period')->pluck('period');
        $statuses = \App\Enums\PmStatus::cases();

        return Inertia::render('Admin/PmRecords/Index', [
            'records' => $records,
            'filters' => $request->only(['q', 'status', 'period', 'year', 'type']),
            'years' => $years,
            'periods' => $periods,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Admin: hapus PM Record secara permanen (hard delete).
     *
     * Menghapus record beserta items, signatures, revisions, dan audit logs terkait.
     */
    public function adminDestroy(Request $request, PmRecord $record): RedirectResponse
    {
        $this->authorize('delete', $record);

        $info = [
            'id' => $record->id,
            'machine' => $record->machine?->code,
            'period' => $record->period,
            'year' => $record->year,
            'status' => $record->status->value,
            'technician_name' => $record->technicianName(),
        ];

        // Hapus relasi terkait (cascade sudah diatur di migration, tapi pastikan)
        $record->items()->delete();
        $record->signatures()->delete();
        $record->revisions()->delete();

        // Hapus audit logs terkait
        \App\Models\AuditLog::where('subject_type', PmRecord::class)
            ->where('subject_id', $record->id)
            ->delete();

        $record->delete();

        return back()->with('success', "PM Record untuk {$info['machine']} ({$info['period']} {$info['year']}) berhasil dihapus permanen.");
    }

    /**
     * Ambil PM Record dengan timeline untuk modal riwayat di halaman Mesin.
     */
    public function pmHistory(Request $request, Machine $machine): Response
    {
        $period = Period::tryFrom($request->query('period', Period::current()->value)) ?? Period::current();
        $year = (int) $request->query('year', now()->year);

        $record = PmRecord::with(['items', 'signatures', 'picUser'])
            ->where('machine_id', $machine->id)
            ->where('year', $year)
            ->where('period', $period->value)
            ->first();

        if (!$record) {
            $payload = [
                'machine' => [
                    'id' => $machine->id,
                    'code' => $machine->code,
                    'name' => $machine->name,
                ],
                'record' => null,
                'period' => $period->value,
                'year' => $year,
                'error' => 'PM Record tidak ditemukan untuk periode ini.',
            ];

            if ($request->wantsJson()) {
                return response()->json($payload);
            }

            return Inertia::render('Pm/HistoryModal', $payload);
        }

        // Build timeline (reuse logic from buildTimeline)
        $events = [];

        if ($record->submitted_at) {
            $events[] = [
                'time' => $record->created_at?->format('d/m/Y H:i'),
                'label' => 'Dokumen dibuat',
                'detail' => 'Checklist dikirim untuk persetujuan',
                'type' => 'created',
            ];
        }

        foreach ($record->allRejections() as $rejection) {
            $changes = $rejection->getAttribute('changes');
            $reason = is_array($changes) ? ($changes['reason'] ?? null) : null;
            $events[] = [
                'time' => $rejection->created_at?->format('d/m/Y H:i'),
                'label' => 'Ditolak',
                'detail' => $reason ? "Alasan: {$reason}" : 'Tidak ada alasan',
                'type' => 'rejected',
                'by' => $rejection->user?->name,
            ];
        }

        foreach ($record->signatures->sortBy('signed_at') as $sig) {
            $stageLabel = match ($sig->stage->value) {
                'technician' => 'Teknisi',
                'pic' => 'User PIC',
                'supervisor' => 'Atasan',
                default => $sig->stage->label(),
            };
            $events[] = [
                'time' => $sig->signed_at?->format('d/m/Y H:i'),
                'label' => "Ditandatangani oleh {$stageLabel}",
                'detail' => $sig->signed_by_name ?? 'Tidak diketahui',
                'type' => 'signed',
                'note' => $sig->note,
            ];
        }

        if ($record->updated_at) {
            $events[] = [
                'time' => $record->updated_at?->format('d/m/Y H:i'),
                'label' => 'Terakhir diperbarui',
                'detail' => 'Data checklist atau status berubah',
                'type' => 'updated',
            ];
        }

        usort($events, function ($a, $b) {
            return strtotime(str_replace('/', '-', $a['time'])) <=> strtotime(str_replace('/', '-', $b['time']));
        });

        $payload = [
            'machine' => [
                'id' => $machine->id,
                'code' => $machine->code,
                'name' => $machine->name,
            ],
            'record' => [
                'id' => $record->id,
                'status' => $record->status->value,
                'status_label' => $record->status->label(),
                'technician_name' => $record->technicianName(),
                'revision_count' => $record->revision_count,
                'general_note' => $record->general_note,
                'timeline' => $events,
            ],
            'period' => $period->value,
            'year' => $year,
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Pm/HistoryModal', $payload);
    }
}
