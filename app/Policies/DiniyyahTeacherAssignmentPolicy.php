<?php

namespace App\Policies;

use App\Models\DiniyyahTeacherAssignment;
use App\Models\User;

/**
 * Defense-in-depth untuk guard hapus penugasan: penugasan yang masih memiliki
 * jurnal kelas tidak boleh dihapus (cascadeOnDelete FK jurnal→assignment akan
 * menghapus seluruh jurnal permanen — satu-satunya jalur hilangnya data jurnal).
 *
 * Trait {@see \App\Filament\Concerns\HasRoleBasedResourceAccess} memanggil
 * policyAllows('delete', $record) pada canDelete(), sehingga policy ini
 * ditegakkan otomatis di Filament tanpa membutuhkan gate manual.
 */
class DiniyyahTeacherAssignmentPolicy
{
    public function delete(User $user, DiniyyahTeacherAssignment $record): bool
    {
        return $record->isDeletable();
    }

    public function forceDelete(User $user, DiniyyahTeacherAssignment $record): bool
    {
        return $record->isDeletable();
    }
}