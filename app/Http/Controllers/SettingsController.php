<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Halaman Settings: pusat kartu menu pengelolaan aset (Mesin, Utility,
     * Komponen / Parts, Suku Cadang). Halaman ini menggantikan item menu
     * terpisah yang dulu tampil sendiri-sendiri di sidebar; route lama tetap
     * ada, hanya aksesnya kini lewat kartu di sini.
     */
    public function index(): Response
    {
        return Inertia::render('Settings/Index');
    }
}
