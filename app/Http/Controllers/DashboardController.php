<?php

namespace App\Http\Controllers;

use App\Enums\Period;
use App\Exports\MachineStatusExport;
use App\Services\DashboardReportService;
use App\Services\DashboardService;
use App\Services\PeriodService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        [$year, $period] = $this->resolvePeriod($request);

        $search = $request->query('q', '');
        $status = $request->query('status', 'all');
        $subCategory = (string) $request->query('sub', 'all');

        // Statistik memakai seluruh mesin aktif pada periode ini, bukan hasil
        // filter, supaya angka ringkasan dan dot notifikasi tetap konsisten
        // saat user memfilter status atau mengetik pencarian.
        $machines = DashboardService::machineStatuses($year, $period);
        $stats = DashboardService::stats($machines);

        $subCategories = DashboardService::subCategories();

        if ($subCategory !== 'all' && !$subCategories->contains($subCategory)) {
            $subCategory = 'all';
        }

        $visible = DashboardService::applyFilters($machines, $status, $subCategory, $search);

        return Inertia::render('Dashboard/Index', [
            'machines' => $visible,
            'stats' => $stats,
            'periods' => PeriodService::buildPeriods($year),
            'years' => PeriodService::availableYears(),
            'subCategories' => $subCategories,
            'subCategory' => $subCategory,
            'currentPeriod' => $period->value,
            'currentYear' => $year,
            'search' => $search,
            'statusFilter' => $status,
            'types' => collect(\App\Enums\MachineType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->toArray(),
            'isFuturePeriod' => PeriodService::isFuturePeriod($period, $year),
            // Data terpisah untuk report Mesin & Utility (Beranda baru)
            'reportMachine' => DashboardReportService::build($year, 'machine'),
            'reportUtility' => DashboardReportService::build($year, 'utility'),
            'reportYears' => PeriodService::availableYears(),
        ]);
    }

    /**
     * Ekspor daftar mesin + status PM sesuai filter Beranda yang sedang aktif.
     * File yang dihasilkan identik dengan tabel di layar, termasuk saat user
     * memfilter area/status/mencari, supaya hasil cetak tidak menyesatkan.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer'],
            'period' => ['nullable', 'string'],
            'format' => ['required', 'in:xlsx,pdf'],
            'status' => ['nullable', 'string'],
            'sub' => ['nullable', 'string'],
            'q' => ['nullable', 'string'],
        ]);

        [$year, $period] = $this->resolvePeriod($request);

        $status = $validated['status'] ?? 'all';
        $subCategory = $validated['sub'] ?? 'all';
        $search = $validated['q'] ?? '';

        $filename = "pm-mesin-{$year}-{$period->value}";

        if ($validated['format'] === 'xlsx') {
            return Excel::download(
                new MachineStatusExport($year, $period, $status, $subCategory, $search),
                "{$filename}.xlsx"
            );
        }

        $machines = DashboardService::applyFilters(
            DashboardService::machineStatuses($year, $period),
            $status,
            $subCategory,
            $search
        );

        $pdf = Pdf::loadView('dashboard.pdf', [
            'year' => $year,
            'period' => $period,
            'status' => $status,
            'subCategory' => $subCategory,
            'search' => $search,
            'machines' => $machines,
            // Ringkasan di PDF mengikuti hasil filter supaya cocok dengan
            // baris-baris yang benar-benar tercetak.
            'stats' => DashboardService::stats($machines),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("{$filename}.pdf");
    }

    /**
     * Normalisasi tahun/periode dari query string. Tahun di luar daftar filter
     * dan periode yang belum tiba dikembalikan ke nilai berjalan agar URL lama
     * tidak membuka periode terkunci.
     *
     * @return array{0: int, 1: Period}
     */
    private function resolvePeriod(Request $request): array
    {
        $years = PeriodService::availableYears();
        $year = (int) $request->query('year', now()->year);

        if (!in_array($year, $years, true)) {
            $year = now()->year;
        }

        $period = Period::tryFrom((string) $request->query('period', Period::current()->value)) ?? Period::current();

        if ($period->isFuture($year)) {
            $period = Period::current();
        }

        return [$year, $period];
    }
}
