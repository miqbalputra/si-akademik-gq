# Pencatatan Tasmi dan keamanan data

- Akses input mengikuti penugasan PJ Tasmi aktif pada periode aktif. Guru tanpa penugasan tidak memiliki menu input Tasmi. Kelas yang tersedia mengikuti gender guru.
- Predikat Tasmi (`maqbul`, `jayyid`, `jayyid_jiddan`, `mumtaz`) adalah teks. Jangan memasukkannya ke `score_change_logs.old_score/new_score`, karena kedua kolom tersebut bertipe DECIMAL untuk nilai numerik.
- Kejadian pembuatan/perubahan tetap dicatat di `score_change_logs` dengan kolom angka kosong. Predikat awal tersimpan di `activity_log.properties.attributes`, sedangkan perubahan predikat dan data ujian tersimpan di `activity_log.properties.changes`. Riwayat lama tidak dikonversi atau ditimpa.
- Input, edit, dan soft delete melalui portal guru memakai transaksi agar hasil ujian, audit, dan notifikasi tersimpan atau dibatalkan bersama. Bentrok kunci unik dikembalikan sebagai pesan validasi tanpa menimpa hasil lain.
- Filter juz mencakup rentang naik maupun turun, termasuk Juz 30 sampai 26. Pengelompokan kondisi filter tetap mempertahankan batas akses penguji/wali dan filter lain.
- Perbaikan ini tidak membutuhkan migrasi dan tidak menjalankan perubahan massal pada data lama.

Verifikasi: `php artisan test --compact --filter='TasmiFeatureTest|TasmiReportFeatureTest|NotificationDispatchTest|DiniyyahScoreAuditTest|StudentIdentityEditTest'`.

Pengujian wajib memakai SQLite `:memory:`. Karena SQLite menerima teks pada kolom DECIMAL, pengujian Tasmi memasang trigger khusus dalam database pengujian untuk menolak teks pada kolom angka. Pengujian juga memaksakan kegagalan audit untuk memeriksa rollback dan membandingkan data sebelum/sesudahnya.

Jumlah hasil kosong tidak membuktikan guru lupa mengisi. Bedakan penugasan yang belum dibuat, hasil yang belum tersimpan, dan kegagalan sistem berdasarkan bukti; pemeriksaan ini tidak merekam riwayat percobaan input yang tidak pernah mencapai server.
