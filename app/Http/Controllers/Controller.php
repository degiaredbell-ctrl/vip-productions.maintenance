<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Menyediakan $this->authorize() untuk policy. Permission Spatie sudah
    // dicek lewat Gate::before, jadi permission dan policy bisa dipakai
    // bersamaan tanpa mendaftarkan ulang.
    use AuthorizesRequests;
}