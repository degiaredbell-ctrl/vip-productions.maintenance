<?php

namespace App\Http\Controllers;

use App\Enums\SignatureStage;
use App\Models\PmRecord;
use App\Services\SignatureStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyajikan gambar tanda tangan lewat route ber-otorisasi.
 *
 * Path file disimpan di database dan tidak pernah diekspos ke frontend, jadi
 * gambar tidak bisa diambil tanpa login. URL juga tidak bergantung APP_URL,
 * yang sering tidak sama dengan host dev.
 */
class PmSignatureController extends Controller
{
    public function show(Request $request, PmRecord $record, SignatureStage $stage): Response
    {
        $this->authorize('view', $record);

        $signature = $record->signatures()->where('stage', $stage->value)->firstOrFail();

        $path = SignatureStorage::absolutePath($signature->image_path);

        abort_if($path === null, 404);

        // Cache-Control ditetapkan sesudah response dibuat: BinaryFileResponse
        // menulis Cache-Control sendiri saat dikonstruksi, jadi header yang lewat
        // array konstruktor bisa digabung/tertimpa (mis. jadi "public").
        $response = response()->file($path, [
            'Content-Type' => 'image/png',
        ]);
        $response->headers->set('Cache-Control', 'private, max-age=86400');

        return $response;
    }
}
