<?php

namespace App\Http\Controllers;

use App\Actions\Pm\SignPmRecord;
use App\Actions\Pm\SubmitPmRecord;
use App\Http\Requests\StorePmRecordRequest;
use App\Models\PmRecord;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Pengiriman checklist PM dalam bentuk JSON, dipakai form PM baik saat online
 * maupun saat mengirim ulang antrean yang tersimpan di perangkat ketika
 * jaringan ke server terputus.
 *
 * Setiap kiriman membawa `client_id` (UUID dari perangkat). Kalau koneksi putus
 * setelah server selesai menyimpan tapi sebelum perangkat menerima jawaban,
 * perangkat akan mengirim ulang; `client_id` yang sama dijawab "sudah
 * terkirim" alih-alih ditolak karena record-nya sudah terkunci di rantai
 * approval.
 */
class PmSyncController extends Controller
{
    private const DEDUPE_TTL_DAYS = 30;

    /**
     * Dipanggil perangkat sebelum mengirim antrean: memastikan sesi masih
     * hidup, akun yang login sama dengan pemilik antrean, dan sekaligus
     * menyegarkan cookie XSRF-TOKEN setelah sesi lama kedaluwarsa.
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'user_id' => $request->user()->id]);
    }

    public function store(Request $request): JsonResponse
    {
        $clientId = (string) $request->input('client_id', '');

        abort_unless(preg_match('/^[A-Za-z0-9-]{8,64}$/', $clientId) === 1, 422, 'client_id tidak valid.');

        $cacheKey = 'pm-sync:'.$request->user()->id.':'.$clientId;

        return Cache::lock($cacheKey.':lock', 30)->block(10, function () use ($request, $cacheKey) {
            if (($recordId = Cache::get($cacheKey)) !== null) {
                return $this->success($request, PmRecord::find($recordId), duplicate: true);
            }

            // Validasi + otorisasi yang sama persis dengan submit biasa.
            $form = app(StorePmRecordRequest::class);
            $data = $form->validated();

            $record = app(SubmitPmRecord::class)->handle($data);

            Cache::put($cacheKey, $record->id, now()->addDays(self::DEDUPE_TTL_DAYS));

            $this->recordDelayedSubmission($request, $record);

            try {
                app(SignPmRecord::class)->handle(
                    $record,
                    $data['signature'] ?? null,
                    $data['note'] ?? null,
                    $data['technician_name'],
                    $form->usesSavedSignature(),
                    $form->savesSignatureToProfile(),
                );
            } catch (\Throwable $exception) {
                report($exception);

                return response()->json([
                    'ok' => true,
                    'signed' => false,
                    'message' => 'Checklist PM tersimpan, tetapi tanda tangan gagal disimpan. Buka kembali PM untuk menandatangani.',
                    'redirect' => $this->redirectUrl($request),
                ]);
            }

            return $this->success($request, $record);
        });
    }

    private function success(Request $request, ?PmRecord $record, bool $duplicate = false): JsonResponse
    {
        $message = 'Checklist PM tersimpan dan sudah ditandatangani. Menunggu persetujuan User PIC.';

        // Kiriman dari tombol Submit langsung berpindah halaman; pesannya
        // ditampilkan lewat flash seperti submit biasa. Kiriman latar belakang
        // dari antrean menampilkan pesannya sendiri di perangkat.
        if ($request->boolean('foreground')) {
            $request->session()->flash('success', $message);
        }

        return response()->json([
            'ok' => true,
            'signed' => true,
            'duplicate' => $duplicate,
            'record_id' => $record?->id,
            'message' => $message,
            'redirect' => $this->redirectUrl($request),
        ]);
    }

    private function redirectUrl(Request $request): string
    {
        return $request->session()->pull('pm_dashboard_url', route('dashboard'));
    }

    /**
     * Checklist yang diisi saat tidak ada jaringan baru sampai di server
     * belakangan. Waktu pengisian aslinya dicatat di audit log supaya
     * keterlambatan tanggal submit tetap bisa dijelaskan.
     */
    private function recordDelayedSubmission(Request $request, PmRecord $record): void
    {
        $queuedAt = $request->input('queued_at');

        if (! is_string($queuedAt) || $queuedAt === '') {
            return;
        }

        try {
            $filledAt = CarbonImmutable::parse($queuedAt);
        } catch (\Throwable) {
            return;
        }

        // Hanya dicatat kalau memang tertunda, dan tidak menerima waktu dari
        // masa depan (jam perangkat yang salah).
        if ($filledAt->isFuture() || $filledAt->diffInMinutes(now()) < 5) {
            return;
        }

        AuditLogService::record('pm.offline_sync', PmRecord::class, $record->id, [
            'diisi_di_perangkat' => $filledAt->setTimezone(config('app.timezone'))->format('Y-m-d H:i'),
            'terkirim' => now()->format('Y-m-d H:i'),
        ]);
    }
}
