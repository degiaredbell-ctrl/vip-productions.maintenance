<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function view(User $user): bool
    {
        return $user->can('user.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('user.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('user.manage') && self::mayTouch($user, $model);
    }

    public function updatePassword(User $user, User $model): bool
    {
        return $user->can('user.manage') && self::mayTouch($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('user.manage') && $user->id !== $model->id && self::mayTouch($user, $model);
    }

    /**
     * Role Admin hanya bisa diberikan oleh Admin. Role lain yang diberi
     * permission Kelola Pengguna tidak melihat pilihan ini, dan server
     * menolaknya bila dikirim langsung.
     */
    public static function canAssignAdmin(User $user): bool
    {
        return $user->hasRole(Role::Admin->value);
    }

    /**
     * Akun Admin hanya boleh diubah/di-reset/dihapus oleh Admin. Tanpa ini,
     * pemegang Kelola Pengguna bisa mengganti password Admin lalu masuk
     * sebagai Admin, sehingga pembatasan role Admin tidak ada artinya.
     */
    public static function mayTouch(User $user, User $model): bool
    {
        return self::canAssignAdmin($user) || ! $model->hasRole(Role::Admin->value);
    }
}
