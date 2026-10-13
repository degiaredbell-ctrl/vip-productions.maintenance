<?php

namespace App\Services;

use App\Enums\SignatureStage;
use App\Models\PmRecord;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Penyimpanan gambar tanda tangan (PNG dari canvas browser) di disk publik.
 *
 * File disimpan di storage/app/public/signatures/... dan disajikan lewat route
 * ber-otorisasi (PmSignatureController untuk dokumen PM, ProfileController
 * untuk tanda tangan tersimpan per akun), bukan path mentah. Dengan begitu nama
 * file tidak bisa ditebak dari luar dan URL gambar tidak bergantung APP_URL.
 *
 * Ada dua keluarga file:
 *
 * - Tanda tangan dokumen (`signatures/pm-record-{id}/...`): snapshot PNG pada
 *   tiap tahap rantai persetujuan. Satu baris per tahap, menandatangani ulang
 *   menimpa baris dan menghapus file lama.
 * - Tanda tangan tersimpan (`signatures/user-{id}/...`): milik akun, dipakai
 *   ulang di panel persetujuan. Saat dipakai, isinya disalin ke keluarga
 *   dokumen supaya dokumen tidak berubah kalau user mengganti tanda tangannya.
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
     * Simpan gambar tanda tangan dokumen dan kembalikan path relatifnya.
     *
     * @throws RuntimeException kalau data URL bukan PNG yang valid atau terlalu besar.
     */
    public static function store(PmRecord $record, SignatureStage $stage, string $dataUrl): string
    {
        return self::putBinary(
            sprintf('signatures/pm-record-%d', $record->id),
            sprintf('%s-%s-%s.png', $stage->value, now()->format('YmdHis'), bin2hex(random_bytes(4))),
            self::decode($dataUrl)
        );
    }

    /**
     * Simpan tanda tangan tersimpan milik user, sebagai pengganti yang lama.
     *
     * Pemanggil (ProfileController atau SignPmRecord saat "simpan ke profil")
     * yang bertanggung jawab menghapus path lama setelah yang baru berhasil
     * ditulis, supaya kegagalan menulis tidak menghilangkan tanda tangan lama.
     */
    public static function storeUserSignature(User $user, string $dataUrl): string
    {
        return self::putBinary(
            sprintf('signatures/user-%d', $user->id),
            sprintf('saved-%s-%s.png', now()->format('YmdHis'), bin2hex(random_bytes(4))),
            self::decode($dataUrl)
        );
    }

    /**
     * Salin tanda tangan tersimpan milik user ke dokumen PM.
     *
     * Pembacaan dan penulisan dilakukan per byte (bukan memindahkan path),
     * supaya setelah disalin dokumen sudah pegang snapshot sendiri dan tidak
     * bergantung pada perubahan tanda tangan di profil.
     *
     * @throws RuntimeException kalau user belum punya tanda tangan tersimpan.
     */
    public static function copyUserSignature(PmRecord $record, SignatureStage $stage, User $user): string
    {
        $binary = self::readBinary($user->signature_path);

        return self::putBinary(
            sprintf('signatures/pm-record-%d', $record->id),
            sprintf('%s-%s-%s.png', $stage->value, now()->format('YmdHis'), bin2hex(random_bytes(4))),
            $binary
        );
    }

    /**
     * Baca byte PNG tanda tangan tersimpan dari path relatif.
     *
     * @throws RuntimeException kalau path kosong/ketahuan file tidak ada.
     */
    public static function readBinary(?string $path): string
    {
        if ($path === null || $path === '') {
            throw new RuntimeException('Tanda tangan tersimpan tidak ditemukan.');
        }

        $binary = Storage::disk(self::DISK)->get($path);

        if ($binary === null || $binary === '') {
            throw new RuntimeException('Tanda tangan tersimpan tidak dapat dibaca.');
        }

        return $binary;
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

    private static function putBinary(string $directory, string $name, string $binary): string
    {
        $path = $directory . '/' . $name;

        Storage::disk(self::DISK)->put($path, $binary);

        return $path;
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