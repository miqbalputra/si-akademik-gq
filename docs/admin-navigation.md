# Navigasi admin dan perpindahan portal

Urutan sidebar: Dasbor, Khusus Admin, Rapor, Data Sekolah, Diniyyah, Tahfidz. Khusus Admin dan Rapor terbuka pada kunjungan pertama. Kelompok aktif selalu terbuka. Pilihan buka/tutup disimpan di browser dengan kunci versi, ID akun, dan perannya; tidak disimpan dalam data akademik.

`App\Support\AdminNavigation` merupakan sumber pemetaan menu: kelas resource/page, kelompok, urutan, label khusus, serta tujuan laporan. Definisi resource/page memakai `shouldRegisterNavigation()` dan `canAccess()` yang sudah ada. Hak melihat/mengedit data, policy per-record, dan URL halaman tidak diubah. Penjagaan akses tetap berjalan saat membuka URL langsung dan pada pembaruan Livewire.

| Kelompok | Isi utama |
| --- | --- |
| Khusus Admin | Pengguna, arsip santri, penempatan santri, matriks jadwal sesi, konfigurasi AI RPP, tampilan |
| Rapor | Rapor Diniyyah, peringkat semua kelas |
| Data Sekolah | Identitas sekolah/santri/wali/guru, kalender dan periode, struktur kelas, presensi dan status integrasinya |
| Diniyyah | Sesi, mapel, KKM, penugasan, jadwal, jurnal, penilaian, RPP/Promes, laporan JP dan pengingat jurnal |
| Tahfidz | Halaqah, pekan, UAS, penguji Tasmi, hasil Tasmi, rekap dan ekspor |

Leger tetap khusus portal wali kelas terkait. Resource leger admin, halaman demo, dan audit yang sebelumnya tersembunyi tidak diaktifkan. Peringkat tetap khusus admin. Laporan/ekspor Tasmi tetap hanya untuk admin dan kabag Tahfidz, sedangkan halaman hasil Tasmi mempertahankan akses baca kepala sekolah.

## Perpindahan sesi

- `POST workspace.open-panel`: guard `web`; menerima `destination` berupa kunci registri, bukan URL; memeriksa izin lalu memakai `WorkspaceRedirectService::switchToAdminGuard()`.
- `POST workspace.open-portal`: guard `admin`; menerima `workspace` milik akun selain management; memindahkan akun yang sama ke guard `web`.
- Kedua endpoint memakai middleware web/CSRF, meregenerasi ID sesi, dan membersihkan tujuan login lama. GET tidak melakukan perpindahan. Guard tetap terpisah; membuka `/admin` melalui sesi web tidak otomatis mengautentikasi panel.
- Komponen `workspace-link` merender form POST bila tujuan memerlukan perpindahan guard, dan tautan biasa untuk navigasi dalam guard yang sama. Menu akun Filament memakai `postToUrl()` untuk kembali ke portal.
- Shell laporan menentukan logout dan notifikasi berdasarkan guard yang sedang digunakan, bukan teks judul portal. Seluruh login tetap melalui `/login`.

## Pencarian dan pemeriksaan

Pencarian hanya menyaring DOM menu yang sudah diizinkan server, tanpa API/database baru. Pencarian membuka kelompok hasil sementara, lalu mengembalikan posisi kelompok saat dibersihkan. Escape membersihkan pencarian sebelum menutup drawer. Tidak ada pencarian yang ditulis ke data akademik.

Verifikasi utama: `AdminNavigationTest`, `NavigationDataSafetyTest`, `AdminDashboardLivewireTest`, dan pengujian login/workspace yang sudah ada. Pengujian tujuan membuka seluruh halaman menu admin, memeriksa kelengkapan dan pembagian peran, serta menolak URL tujuan bebas dan perpindahan ke peran yang tidak dimiliki.

Pengujian data memakai SQLite `:memory:`. `NavigationDataSafetyTest` membandingkan isi tabel identitas, presensi, nilai, penugasan, leger terkunci, dan rapor terbit sebelum/sesudah navigasi serta perpindahan portal. Tidak ada migrasi, seeder operasional, backfill, atau perubahan massal pada data lama.

Pratinjau visual lokal dapat diekspor dari pengujian dengan `GQ_NAV_PREVIEW_DIR` ke direktori sementara. Pratinjau menggunakan akun fiktif dan aset hasil build, bukan database operasional. Periksa viewport 360/390/768/1366/1920 px, kelompok buka-tutup, pencarian, dan ukuran tombol minimal 44 px.
