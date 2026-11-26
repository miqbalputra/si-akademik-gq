# Navigasi portal guru

Definisi menu guru berada di `app/Support/GuruNavigation.php`. Shell portal yang sama menampilkan definisi tersebut di desktop dan sidebar mobile. Perubahan ini hanya presentasi: rute, controller, policy, formulir, dan proses akademik tetap menggunakan implementasi sebelumnya.

Urutannya Beranda, Mengajar, Tahfidz & Tasmi, Kelas Saya, RPP & Program Semester, Riwayat Saya, Koordinasi, lalu Rapor. Kelompok Rapor dibuka secara bawaan dan dimulai dengan **Alur Nilai & Rapor**, dilanjutkan tautan input nilai Diniyyah, KKM, dan input final Tahfidz. Tautan Leger Kelas Saya dan Rapor Kelas Saya tetap tersedia bagi wali kelas sesuai visibilitas sebelumnya. Panduan kelas hanya menampilkan kelas dengan penugasan aktif. Controller dan policy tujuan memeriksa hak akses masing-masing. Kelompok kosong disembunyikan.

Saat menambah menu, isi label yang jelas, rute, pola halaman aktif, dan alias pencarian bila ada nama lama. Gunakan `except` untuk halaman yang memiliki menu sendiri, seperti Riwayat Jurnal. Setiap tujuan memiliki satu kategori. Detail, ekspor, cetak, dan riwayat Tasmi tetap berada di halaman induk.

`resources/js/guru-navigation.js` mengatur pencarian lokal dan preferensi kelompok di localStorage per akun dan peran. Tidak ada penyimpanan preferensi di database. Mengajar terbuka pada kunjungan pertama, Rapor juga terbuka untuk wali kelas. Kelompok aktif selalu terbuka; pencarian membuka hasil dan memulihkan preferensi setelah dihapus. Escape pertama menghapus pencarian, Escape berikutnya menutup sidebar mobile. Sidebar mobile yang tertutup tidak menerima fokus keyboard.

Validasi otomatis menggunakan SQLite `:memory:` yang diwajibkan `tests/TestCase.php`:

```
php artisan test --compact --filter="GuruNavigationTest|PortalShellNavigationTest|NavigationDataSafetyTest|LeadershipWorkspaceTest|WorkspaceSelectionTest|UnifiedLoginTest|GuruJadwalRiwayatTest|TahfidzReportInputTest|ReportCardF4Test|TasmiFeatureTest|AdminNavigationTest"
node --test tests/js/guru-navigation.test.mjs tests/js/admin-navigation.test.mjs
npm run build
```

Uji tampilan pada lebar 360, 390, 768, dan 1366 piksel. Periksa buka/tutup mobile, label panjang, pencarian nama lama, hasil kosong, preferensi setelah muat ulang, satu menu aktif, serta area sentuh minimal 44 piksel. Pratinjau harus memakai data uji; jangan membuat data akademik di produksi untuk menguji navigasi. Rilis ini tidak membutuhkan migrasi atau pembaruan data.
