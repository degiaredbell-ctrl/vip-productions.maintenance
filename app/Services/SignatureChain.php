<?php

namespace App\Services;

use App\Enums\PmStatus;
use App\Enums\Role;
use App\Enums\SignatureStage;
use App\Models\PmRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sumber kebenaran siapa yang boleh menandatangani tahap mana, jadi policy,
 * halaman Persetujuan, dan badge sidebar tidak masing-masing menulis aturan
 * sendiri.
 */
class SignatureChain
{
    /**
     * Tahap yang sedang menunggu user ini, sesuai permission yang dimilikinya.
     *
     * @return array<int, SignatureStage>
     */
    public static function stagesFor(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return array_values(array_filter(
            SignatureStage::ordered(),
            fn (SignatureStage $stage) => $user->can($stage->permission())
        ));
    }

    /**
     * Status pm_records yang sedang menunggu keputusan user ini.
     *
     * @return array<int, string>
     */
    public static function pendingStatusesFor(?User $user): array
    {
        $stages = self::stagesFor($user);

        return array_values(array_map(
            fn (PmStatus $status) => $status->value,
            array_filter(
                PmStatus::cases(),
                fn (PmStatus $status) => $status->awaiting() !== null
                    && in_array($status->awaiting(), $stages, true)
            )
        ));
    }

    /**
     * User yang boleh dipilih teknisi sebagai User PIC tahap berikutnya.
     *
     * Dipakai permission tahap PIC sebagai patokan, bukan nama role hard-coded,
     * supaya kalau hak akses PIC berubah daftar pilihan ikut berubah tanpa perlu
     * menyentuh kueri. User yang sedang mengisi checklist tidak mungkin masuk
     * daftar ini karena role-nya berbeda, dan `canSign` juga tetap menolak
     * teknisi menandatangani tahapnya sendiri.
     *
     * @return Builder<User>
     */
    public static function picCandidates(): Builder
    {
        return User::query()
            ->role(Role::User->value)
            ->orderBy('name');
    }

    /**
     * Status pm_records yang tahapnya menunggu User PIC.
     *
     * Dipisah supaya penyaringan antrean tidak menuliskan nama status secara
     * manual; menambah tahap baru cukup menambah enum, bukan kueri.
     *
     * @return array<int, string>
     */
    public static function picPendingStatuses(): array
    {
        return array_values(array_map(
            fn (PmStatus $status) => $status->value,
            array_filter(
                PmStatus::cases(),
                fn (PmStatus $status) => $status->awaiting() === SignatureStage::Pic
            )
        ));
    }

    /**
     * Query record yang benar-benar menunggu tindakan user ini.
     *
     * Badge sidebar dan daftar di halaman Persetujuan memakai query yang sama;
     * kalau badge menghitung lebih banyak dari yang bisa dikerjakan, user akan
     * menekan menu lalu menemukan daftar yang lebih pendek tanpa penjelasan.
     *
     * @return \Illuminate\Database\Eloquent\Builder<PmRecord>
     */
    public static function pendingQuery(?User $user): Builder
    {
        $stages = self::stagesFor($user);
        $statuses = self::pendingStatusesFor($user);
        $isAdmin = $user !== null && $user->hasRole(Role::Admin->value);

        return PmRecord::query()
            ->whereIn('status', $statuses)
            ->when(
                // Record tahap teknisi hanya relevan untuk teknisi yang
                // mengisinya. Admin melihat semuanya karena boleh menandatangani
                // tahap mana pun.
                in_array(SignatureStage::Technician, $stages, true) && ! $isAdmin,
                fn (Builder $q) => $q->where(
                    fn (Builder $inner) => $inner
                        ->whereNull('technician_id')
                        ->orWhere('technician_id', $user->id)
                )
            )
            ->when(
                // Penugasan User PIC oleh teknisi membatasi siapa yang boleh
                // menyetujui tahap 2. Record lama yang belum ditugaskan tetap
                // terbuka untuk semua User PIC supaya tidak ada antrean lama
                // yang mandek tanpa jalan keluar.
                in_array(SignatureStage::Pic, $stages, true) && ! $isAdmin,
                fn (Builder $q) => $q->where(function (Builder $inner) use ($user) {
                    $inner->whereNotIn('status', self::picPendingStatuses())
                        ->orWhereNull('pic_user_id')
                        ->orWhere('pic_user_id', $user->id);
                })
            );
    }

    /**
     * Jumlah record yang menunggu user ini. Dipakai untuk badge sidebar
     * supaya user tahu ada yang perlu ditandatangani tanpa membuka menu.
     */
    public static function pendingCountFor(?User $user): int
    {
        if (!$user) return 0;

        if (self::pendingStatusesFor($user) === []) return 0;

        return self::pendingQuery($user)->count();
    }

    /**
     * Technician yang mengisi checklist memang menandatanganinya sendiri pada
     * tahap 1 — itu justru titik tanda tangannya, bukan konflik kepentingan.
     *
     * Yang ditahan adalah tahap 2 dan 3: approver tidak boleh orang yang sama
     * dengan mengisi checklist, supaya persetujuan di hilir tidak berubah jadi
     * formalitas. Admin tetap lolos di semua tahap karena ia tidak melakukan
     * pekerjaan lapangan, dan tetap dibutuhkan sebagai pengecualian ketika
     * tidak ada orang lain yang bisa menandatangani.
     */
    public static function canSign(?User $user, PmRecord $record): bool
    {
        if (!$user) return false;

        $stage = $record->status->awaiting();

        if ($stage === null) return false;

        if (!$user->can($stage->permission())) return false;

        if ($stage === SignatureStage::Technician) {
            // Technician menandatangani checklist miliknya sendiri, bukan
            // pekerjaan teknisi lain. Admin tetap boleh karena tidak mengisi
            // checklist tersebut.
            return $user->hasRole(Role::Admin->value)
                || $record->technician_id === null
                || $record->technician_id === $user->id;
        }

        // Penugasan User PIC oleh teknisi membatasi siapa yang boleh menyetujui
        // tahap 2. Record lama yang belum ditugaskan tidak dibatasi.
        if ($stage === SignatureStage::Pic && $record->hasAssignedPic() && ! self::isAssignedPic($user, $record)) {
            return false;
        }

        return $record->technician_id === null || $record->technician_id !== $user->id;
    }

    /**
     * Penolakan hanya bisa terjadi di tahap User PIC atau Atasan. Technician
     * yang menolak karyawannya sendiri bukan jalur yang tersedia di sistem ini;
     * ia cukup memperbaiki checklist lalu menandatangani ulang.
     */
    public static function canReject(?User $user, PmRecord $record): bool
    {
        if (!$user) return false;

        $stage = $record->status->awaiting();

        if ($stage === null || $stage === SignatureStage::Technician) return false;

        if (!$user->can($stage->permission())) return false;

        if ($stage === SignatureStage::Pic && $record->hasAssignedPic() && ! self::isAssignedPic($user, $record)) {
            return false;
        }

        return $record->technician_id === null || $record->technician_id !== $user->id;
    }

    /**
     * Apakah user ini User PIC yang dipilih teknisi untuk record ini.
     *
     * Admin tetap dianggap benar supaya menjadi cadangan ketika User PIC yang
     * dipilih sedang tidak bisa menandatangani. Tanpa pengecualian ini, satu
     * orang yang salah pilih akan mengunci antrean tanpa ada yang bisa
     * membukanya.
     */
    private static function isAssignedPic(User $user, PmRecord $record): bool
    {
        return $user->hasRole(Role::Admin->value) || $record->pic_user_id === $user->id;
    }
}
