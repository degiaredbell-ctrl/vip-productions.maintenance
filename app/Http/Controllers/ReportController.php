<?php

namespace App\Http\Controllers;

use App\Enums\Period;
use App\Models\PmRecord;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PmReportExport;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) $request->query('year', now()->year);
        $period = $request->query('period');

        $compliance = ReportService::complianceByPeriod($year);
        $topParts = ReportService::topReplacedParts($year, $period);
        $actions = ReportService::actionSummary($year, $period);

        // Statistik ikut filter periode supaya angka di kartu atas sinkron
        // dengan Ringkasan Tindakan dan daftar komponen di bawahnya.
        $totalPm = PmRecord::where('year', $year)
            ->when($period, fn ($q) => $q->where('period', $period))
            ->count();
        $totalApproved = PmRecord::where('year', $year)
            ->where('status', 'approved')
            ->when($period, fn ($q) => $q->where('period', $period))
            ->count();

        return Inertia::render('Reports/Index', [
            'compliance' => $compliance,
            'topParts' => $topParts,
            'actions' => $actions,
            'totalPm' => $totalPm,
            'totalApproved' => $totalApproved,
            'year' => $year,
            'period' => $period,
            'periods' => collect(Period::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()])->toArray(),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'year' => ['required', 'integer'],
            'period' => ['nullable', 'string'],
            'format' => ['required', 'in:xlsx,pdf'],
        ]);

        $year = $request->input('year');
        $period = $request->input('period');
        $format = $request->input('format');

        if ($format === 'xlsx') {
            return Excel::download(new PmReportExport($year, $period), "pm-report-{$year}.xlsx");
        }

        // PDF export
        $data = [
            'year' => $year,
            'period' => $period,
            'compliance' => ReportService::complianceByPeriod($year),
            'topParts' => ReportService::topReplacedParts($year, $period),
            'actions' => ReportService::actionSummary($year, $period),
            // Detail komponen diganti + mesinnya, dibatasi tahun/periode yang
            // sedang diekspor supaya cocok dengan angka di halaman laporan.
            'replacedParts' => ReportService::replacedPartsDetail($year, $period),
            'logoDataUri' => $this->logoDataUri(),
            'generatedAt' => now(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', $data);
        return $pdf->download("pm-report-{$year}.pdf");
    }

    /**
     * Logo aplikasi sebagai data URI (base64).
     *
     * DomPDF tidak punya akses HTTP saat render, jadi aset publik harus
     * ditanam langsung ke dalam dokumen. Mengembalikan null kalau file logo
     * tidak ada supaya cetak tetap jalan tanpa gambar.
     */
    private function logoDataUri(): ?string
    {
        $path = public_path('img/logo.png');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }
}
