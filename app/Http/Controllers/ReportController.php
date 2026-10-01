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
        $topParts = ReportService::topReplacedParts();
        $actions = ReportService::actionSummary($year, $period);

        $totalPm = PmRecord::where('year', $year)->count();
        $totalApproved = PmRecord::where('year', $year)->where('status', 'approved')->count();

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
            'topParts' => ReportService::topReplacedParts(),
            'actions' => ReportService::actionSummary($year, $period),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', $data);
        return $pdf->download("pm-report-{$year}.pdf");
    }
}
