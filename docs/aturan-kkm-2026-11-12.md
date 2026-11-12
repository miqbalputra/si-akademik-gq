# Aturan KKM sekolah — 12 November 2026

Rata-rata dihitung dari tiga angka komponen (Kompleksitas, Daya Dukung, Intake), masing-masing bilangan bulat 60–100. Rata-rata asli digunakan untuk menentukan KKM, tanpa pembulatan terlebih dahulu:

| Rata-rata | KKM akhir |
| --- | --- |
| Kurang dari 70 | 65 |
| 70 sampai kurang dari 75 | 70 |
| 75 atau lebih | 75 |

Contoh batas: 209 ÷ 3 = 69,666… menghasilkan 65; 224 ÷ 3 = 74,666… menghasilkan 70. Angka rata-rata pada layar dan ekspor ditampilkan dengan dua desimal, tetapi penentuan kategori memakai hasil pembagian asli. Kategori dan rentang angka setiap komponen tetap sama. Suluk dan Praktik Ibadah tetap mengikuti ketetapan sekolah 70.

## Penyesuaian data yang sudah ada

Migration `2026_11_12_120000_apply_banded_kkm_policy` menjalankan penyesuaian pada semester dengan alur KKM aktif yang masih berstatus aktif atau tanggal akhirnya belum lewat. Kelompok mapel–mustawa yang memiliki leger/rapor terkunci atau terbit dilewati, termasuk kelas yang diarsipkan.

- Draf, pengajuan menunggu ACC, dan versi perlu revisi dihitung ulang dari komponen yang tersimpan. Alasan, status, dan identitas/waktu pengajuan dipertahankan; `lock_version` bertambah sehingga halaman lama harus dimuat ulang. Draf belum lengkap tetap tidak memiliki KKM akhir.
- Jika KKM resmi lama berubah, sistem membuat versi resmi baru dengan penetap **Sekolah (penyesuaian aturan KKM)** dan referensi ke versi asal. Versi sebelumnya menjadi riwayat; komponen, alasan, dan data persetujuan lamanya dipertahankan. Versi kerja yang masih ada tetap menjadi versi kerja.
- Jika KKM resmi lama sudah sesuai dengan kategori baru, ambang resmi dan dokumen terkait tidak diubah.
- Perubahan KKM resmi disinkronkan ke mapel kelas, penilaian, hasil penilaian, dan status ketuntasan. Angka nilai siswa tidak diubah. Penilaian tervalidasi kembali menjadi pengajuan, dan leger terbuka ditandai perlu dibuat ulang. Rapor draf dengan versi KKM lama harus dibuat ulang sebelum dikunci, mengikuti pemeriksaan versi yang sudah ada.
- Riwayat versi lama tetap menampilkan nilai dan aturan perhitungannya sendiri pada ekspor. Versi baru menyimpan penanda aturan kategori.
- Isian historis yang tidak valid tidak ditebak atau dihapus: dilewati dan dicatat pada log bersama ID versi untuk pemeriksaan manual.

## Penerapan

Penyesuaian data dijalankan oleh migration saat deployment memakai prosedur migrasi aplikasi yang sudah ada. Pengujian menggunakan SQLite `:memory:`; tidak menjalankan penyesuaian pada database produksi.

Penyesuaian dapat dijalankan ulang tanpa membuat versi penyesuaian ganda. `down()` tidak menghapus riwayat atau mengembalikan KKM ke aturan lama.
