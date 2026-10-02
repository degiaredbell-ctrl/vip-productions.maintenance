<?php

namespace App\Services;

use App\Enums\SignatureStage;
use App\Models\PmRecord;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Penyimpanan gambar tanda tangan (PNG dari canvas browser) di disk publik.
 *
 * File disimpan di storage/app/public/signatures/... dan disajikan lewat route
 * ber-otorisasi (PmSignatureController), bukan path mentah. Dengan begitu nama
 * file tidak bisa ditebak dari luar dan URL gambar tidak bergantung APP_URL.
 */
class SignatureStorage
{
    public const DISK = 'public';

    /** Batas ukuran PNG setelah di-decode: 2 MB. */
    public const MAX_BYTES = 2_097_152;

    /** Batas panjang string data URL (base64 menambah ~33%). */
    public const MAX_CHARS = 2_800_000;

    private const DATA_URL_PREFIX = 'data:image/png;base64,';

    private const PNG_MAGIC = "\x89PNG\r\n\x1a\n";

    /**
     * Simpan gambar tanda tangan dan kembalikan path relatifnya.
     *
     * @throws RuntimeException kalau data URL bukan PNG yang valid atau terlalu besar.
     */
    public static function store(PmRecord $record, SignatureStage $stage, string $dataUrl): string
    {
        $binary = self::decode($dataUrl);

        $directory = sprintf('signatures/pm-record-%d', $record->id);
        $name = sprintf(
            '%s-%s-%s.png',
            $stage->value,
            now()->format('YmdHis'),
            bin2hex(random_bytes(4))
        );

        $path = $directory . '/' . $name;

        Storage::disk(self::DISK)->put($path, $binary);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public static function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && Storage::disk(self::DISK)->exists($path);
    }

    public static function absolutePath(string $path): ?string
    {
        $full = Storage::disk(self::DISK)->path($path);

        return is_file($full) ? $full : null;
    }

    /**
     * Decode data URL PNG dari canvas. Validasi ditegakkan ulang di sini,
     * bukan hanya di form request, supaya file tidak pernah berisi payload
     * bebas yang kebetulan lolos regex.
     */
    private static function decode(string $dataUrl): string
    {
        if (!str_starts_with($dataUrl, self::DATA_URL_PREFIX)) {
            throw new RuntimeException('Gambar tanda tangan harus berformat PNG.');
        }

        $binary = base64_decode(substr($dataUrl, strlen(self::DATA_URL_PREFIX)), true);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Gambar tanda tangan tidak dapat dibaca.');
        }

        if (!str_starts_with($binary, self::PNG_MAGIC)) {
            throw new RuntimeException('Gambar tanda tangan harus berformat PNG.');
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw new RuntimeException('Ukuran gambar tanda tangan terlalu besar.');
        }

        return $binary;
    }
}
