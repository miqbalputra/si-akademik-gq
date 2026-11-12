# Hapus KKM oleh admin

Di **Penetapan KKM → Buka KKM**, akun dengan peran `admin` mendapatkan:

- **Hapus versi** pada setiap rincian riwayat: menghapus satu draf/pengajuan/persetujuan/versi yang dipilih. Versi lain tetap ada. Jika versi resmi yang dihapus, KKM kembali **Menunggu ACC**; versi terdahulu tidak otomatis diaktifkan kembali.
- **Hapus KKM & seluruh riwayat** pada bagian **Pembersihan KKM oleh admin**: membersihkan seluruh versi untuk satu mapel, mustawa, dan semester. Penugasan guru tetap ada sehingga guru dapat menyusun ulang dari awal.

Kedua tindakan memerlukan konfirmasi. Akun guru dan kabag tanpa peran admin tidak dapat menjalankannya. Ketetapan sekolah Suluk/Praktik Ibadah tidak dapat dihapus melalui fitur ini. KKM terkait leger/rapor terkunci atau terbit juga tidak dapat dihapus, termasuk bila kelasnya sudah diarsipkan.

Penghapusan tidak dijalankan oleh deployment atau migration; admin memilih sendiri data yang dibersihkan. Seluruh perubahan dilakukan dalam transaksi dan dihentikan jika isi KKM berubah sejak halaman dibuka. Data versi yang dihapus beserta identitas admin disimpan dalam audit aktivitas.

Saat KKM resmi dihapus, referensinya dilepas dari mapel kelas, penilaian, hasil penilaian, dan rapor draf. Angka komponen/nilai siswa tidak dihapus. Penilaian tervalidasi kembali menjadi pengajuan; leger terbuka ditandai perlu dibuat ulang. Ketuntasan menunggu KKM baru disetujui. Notifikasi pengajuan/ACC yang sudah tidak berlaku diarsipkan, sementara notifikasi penugasan guru tetap tersedia.

Tidak diperlukan migration tambahan untuk fitur ini. Pengujian memakai database SQLite `:memory:`.
