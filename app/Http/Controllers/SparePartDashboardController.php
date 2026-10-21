<?php

namespace App\Http\Controllers;

use App\Services\SparePartDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard monitoring Suku Cadang. Datanya dirakit di
 * SparePartDashboardService supaya sama dengan bagian Suku Cadang di Beranda.
 */
class SparePartDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('SpareParts/Dashboard', SparePartDashboardService::build(
            SparePartDashboardService::normalizeRange($request->query('range')),
            $request->user()->can('sparepart.manage'),
        ));
    }
}
