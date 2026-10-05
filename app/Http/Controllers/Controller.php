<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    // Menyediakan $this->authorize() untuk policy. Permission Spatie sudah
    // dicek lewat Gate::before, jadi permission dan policy bisa dipakai
    // bersamaan tanpa mendaftarkan ulang.
    //
    // Extends BaseController wajib: authorizeResource() (dipakai RoleController)
    // memanggil $this->middleware(), yang hanya ada di Illuminate\Routing\Controller.
    use AuthorizesRequests;
}