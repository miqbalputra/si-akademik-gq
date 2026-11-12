# Validasi alasan dan penghapusan KKM

## Perubahan

- Tiga kolom alasan diberi tanda wajib diisi. Simpan draf dan ajukan ACC menolak alasan kosong atau spasi, baik melalui browser maupun validasi server. Draf tetap boleh memiliki angka yang belum lengkap.
- Pesan kesalahan menggunakan nama unsur dan muncul di dekat kolom terkait. Isian setelah validasi hanya dipulihkan untuk versi KKM yang sama, sehingga isian versi yang sudah dihapus tidak mengisi draf baru.
- ID relasi KKM dinormalisasi menjadi integer melalui model casts. Sebelumnya, bila database mengembalikan ID sebagai string, pemeriksaan ketat gagal mengenali versi aktif/berjalan yang akan dihapus. Referensi scope tidak dilepas sehingga foreign key menolak penghapusan dan transaksi dibatalkan.
- Respons halaman KKM menggunakan `Cache-Control: private, no-store, max-age=0, must-revalidate`. Halaman yang dikembalikan dari cache navigasi browser dimuat ulang untuk memperoleh kondisi terbaru.

## Batas penghapusan dan keamanan data

Tidak ada migrasi, perubahan massal, atau penghapusan database saat memasang perbaikan ini. Penghapusan tetap harus dipilih admin untuk mapel, mustawa, semester, dan versi yang dituju. Percobaan penghapusan yang sebelumnya gagal perlu diulang setelah pembaruan terpasang.

Layanan penghapusan tetap mempertahankan angka nilai siswa, data siswa, dan penugasan guru. Hanya KKM terpilih dan referensi KKM terkait yang dibersihkan. Status ketuntasan perlu dihitung ulang setelah KKM pengganti disetujui; leger/rapor draf terdampak perlu dibuat ulang. Dokumen terkunci/terbit tetap memblokir penghapusan. Audit penghapusan tetap disimpan.

## Verifikasi

- Suite KKM: 113 pengujian, 880 assertions; seluruhnya lolos menggunakan SQLite `:memory:`.
- Regresi ID string mencakup hapus seluruh riwayat, versi resmi saja, dan versi berjalan saja. Angka harian, ujian, dan nilai akhir siswa tetap sama; akun guru tidak lagi melihat angka/alasan/riwayat versi yang sudah dihapus.
- Validasi alasan mencakup nilai null, kosong, dan spasi pada simpan maupun ajukan; kegagalan validasi tidak mengubah data tersimpan.
- Build frontend berhasil. Uji browser lokal mengonfirmasi tanda wajib diisi, pesan peringatan, serta penolakan isian spasi.
- Database produksi tidak diubah selama pengembangan dan pengujian.
