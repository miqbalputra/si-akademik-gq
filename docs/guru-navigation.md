# Navigasi portal guru

Definisi menu guru berada di `app/Support/GuruNavigation.php`. Shell portal yang sama menampilkan definisi tersebut di desktop dan sidebar mobile. Perubahan ini hanya presentasi: rute, controller, policy, formulir, dan proses akademik tetap menggunakan implementasi sebelumnya.

Urutan: Beranda, Mengajar, Tahfidz & Tasmi, Kelas Saya, RPP & Program Semester, Riwayat Saya, Koordinasi, Rapor. Kelompok Rapor selalu paling bawah sebagai tujuan akhir pengolahan nilai. Kelompok kosong disembunyikan. Rapor menampilkan input nilai final tahfidz untuk semua guru; leger dan rapor kelas mengikuti visibilitas wali kelas sebelumnya. Menu wali kelas historis tetap mengikuti perilaku lama, sedangkan akses dan cakupan data tujuan tetap diputuskan oleh controller/policy yang sudah ada. Penugasan presensi, Tafsir, PJ Tasmi, peran kabag, dan konfigurasi sinkronisasi RPP tetap diperhitungkan.

Saat menambah menu, isi label yang jelas, rute, pola halaman aktif, dan alias pencarian bila ada nama lama. Gunakan `except` untuk halaman yang memiliki menu sendiri, seperti Riwayat Jurnal. Setiap tujuan memiliki satu kategori. Detail, ekspor, cetak, dan riwayat Tasmi tetap berada di halaman induk.

`resources/js/guru-navigation.js` mengatur pencarian lokal dan preferensi kelompok di localStorage per akun dan peran. Tidak ada penyimpanan preferensi di database. Mengajar terbuka pada kunjungan pertama, Rapor juga terbuka untuk wali kelas. Kelompok aktif selalu terbuka; pencarian membuka hasil dan memulihkan preferensi setelah dihapus. Escape pertama menghapus pencarian, Escape berikutnya menutup sidebar mobile. Sidebar mobile yang tertutup tidak menerima fokus keyboard.

Validasi otomatis menggunakan SQLite `:memory:` yang diwajibkan `tests/TestCase.php`:

```
php artisan test --compact --filter="GuruNavigationTest|PortalShellNavigationTest|NavigationDataSafetyTest|LeadershipWorkspaceTest|WorkspaceSelectionTest|UnifiedLoginTest|GuruJadwalRiwayatTest|TahfidzReportInputTest|ReportCardF4Test|TasmiFeatureTest|AdminNavigationTest"
node --test tests/js/guru-navigation.test.mjs tests/js/admin-navigation.test.mjs
npm run build
```

Uji tampilan pada lebar 360, 390, 768, dan 1366 piksel. Periksa buka/tutup mobile, label panjang, pencarian nama lama, hasil kosong, preferensi setelah muat ulang, satu menu aktif, serta area sentuh minimal 44 piksel. Pratinjau harus memakai data uji; jangan membuat data akademik di produksi untuk menguji navigasi. Rilis ini tidak membutuhkan migrasi atau pembaruan data.
