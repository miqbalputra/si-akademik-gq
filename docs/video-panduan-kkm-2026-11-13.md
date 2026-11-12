# Video panduan KKM

## Penggunaan

1. Login sebagai admin dan buka menu Penetapan KKM (`/admin/kkm`).
2. Buka bagian **Video panduan KKM**, tempel link YouTube, lalu pilih **Simpan video**.
3. Guru melihat tombol **Lihat Video** di samping **Lihat Panduan** pada daftar KKM dan halaman rincian/formulir KKM yang boleh mereka akses. Link dibuka di tab baru.

Satu link berlaku untuk seluruh guru dan semester, termasuk guru yang tidak ditugaskan sebagai penyusun serta akun guru yang belum memiliki kelas. Hak melihat/mengubah KKM tetap mengikuti penugasan semula. Hanya admin dapat mengubah video; Kabag dan guru tidak dapat menyimpan pengaturan ini.

Format yang didukung: video `youtube.com/watch?v=...`, `youtu.be/...`, Shorts, Live, dan Embed. Link disimpan dalam format HTTPS YouTube standar. Link kanal, playlist tanpa video, domain lain, atau ID video yang tidak valid ditolak. Ketersediaan video di YouTube tetap bergantung pada pemilik video; gunakan video publik atau tidak publik (unlisted) yang dapat ditonton para guru.

Kosongkan link dan simpan untuk menyembunyikan tombol. Sebelum link diatur, tombol video belum ditampilkan.

## Pemasangan dan data

Pembaruan ini menambahkan migrasi `2026_11_13_120000_create_diniyyah_kkm_settings_table.php`. Jalankan migrasi dalam proses deploy sebelum melayani kode baru. Migrasi hanya membuat tabel pengaturan video baru, tanpa mengubah data KKM, pengajuan, penugasan, atau nilai siswa. Pengaturan awal kosong; tidak ada link video contoh yang dipasang ke database aplikasi. Perubahan oleh admin tercatat pada audit KKM.

Database produksi tidak diubah selama implementasi. Pengujian otomatis berjalan menggunakan SQLite `:memory:`; pratinjau browser menggunakan HTML dari data pengujian.

## Verifikasi

- Pengaturan tambah/ganti/hapus link, validasi format URL, perlindungan akses hanya admin, serta tampilan untuk guru penyusun, guru pembaca, dan guru tanpa penugasan.
- Pengaturan video tidak mengubah scope/revisi KKM dan angka nilai siswa yang sudah tersimpan.
- Pratinjau browser memeriksa posisi tombol dan formulir pengaturan admin.
