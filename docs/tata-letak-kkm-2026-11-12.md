# Tata letak pengelolaan KKM

Halaman KKM Kabag Diniyyah dan Admin kini membuka bagian **Status KKM** terlebih dahulu. **Guru Penyusun** berada pada bagian terpisah sehingga daftar penugasan tidak mendorong status pengajuan ke bawah halaman.

- Ringkasan **Menunggu ACC**, **Perlu revisi**, dan **Disetujui** membuka daftar dengan filter terkait. Ringkasan **Belum ditugaskan** membuka bagian Guru Penyusun. Hitungan status mengikuti versi berjalan, atau versi resmi bila tidak ada versi berjalan; ketetapan sekolah tetap memiliki status tersendiri.
- Ringkasan mencakup seluruh kelompok pada semester/mustawa terpilih, bukan hanya halaman pagination atau status yang sedang difilter. Kelompok tanpa penyusun diambil dari rekap pengajar, termasuk mapel yang belum memiliki scope KKM tersimpan.
- Satu pilihan semester digunakan bersama oleh status dan rekap penyusun. Awalnya menggunakan semester aktif, atau semester terakhir jika belum ada semester aktif. Guru tetap menggunakan pilihan semester pribadi seperti sebelumnya.
- Filter semester dan mustawa terbawa saat berpindah bagian. Parameter `view=status` dan `view=assignments` memilih bagian. URL lama dengan `status=unassigned` membuka bagian penugasan.
- Pengajuan menunggu ACC memiliki tombol **Tinjau pengajuan**. Status menggunakan warna berbeda; KKM resmi tetap ditampilkan terpisah dari status revisi berjalan.
- Formulir ganti penyusun dan rincian pengajar/jadwal dibuka bila diperlukan. Di ponsel daftar disusun vertikal dan filter dapat dibuka/tutup.
- Nama semester yang sudah memuat tahun ajaran tidak ditambahkan tahun yang sama sekali lagi. Navigasi kembali dari penugasan massal menuju bagian Guru Penyusun.

Perubahan ini tidak menjalankan migration atau perubahan data KKM/nilai. Alur penugasan, pengajuan, ACC, penghapusan, dan perlindungan dokumen terkunci tetap memakai layanan yang sudah ada.
