<?php

namespace App\Http\Controllers;

use App\Models\DiniyyahScheduleChangeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuruJadwalController extends Controller
{
    /**
     * Halaman "Riwayat Perubahan Jadwal Saya" — menampilkan seluruh perubahan
     * jadwal mengajar & penugasan yang menyangkut guru login, baik sebagai guru
     * pemilik setelah perubahan (teacher_id) maupun guru lama saat pertukaran
     * (old_teacher_id). Dipisah dari halaman input supaya guru fokus mengisi
     * jurnal; riwayat dibuka on-demand. Pola meniru
     * {@see \App\Http\Controllers\GuruDiniyyahJournalController::riwayat()}.
     */
    public function riwayat(Request $request)
    {
        $teacher = Auth::user()?->teacher;
        if (! $teacher) {
            abort(403, 'Akses ditolak. Akun Anda tidak terhubung dengan data Guru.');
        }

        $changes = DiniyyahScheduleChangeLog::with(['teacher', 'oldTeacher', 'changer'])
            ->where('teacher_id', $teacher->id)
            ->orWhere('old_teacher_id', $teacher->id)
            ->orderByDesc('created_at')
            ->get();

        return view('guru.jadwal.riwayat', compact('changes', 'teacher'));
    }
}