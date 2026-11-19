## Alur
1. Pelanggan booking → transfer → upload bukti → status **"Menunggu verifikasi admin"** (jadwal BELUM aktif).
2. Admin menerima **WhatsApp** berisi data pelanggan dan foto bukti.
3. Admin **cukup membalas langsung di WhatsApp**:
   - `ACC FS-XXXXXX` → pembayaran valid
   - `TOLAK FS-XXXXXX alasan...` → batalkan & hapus pendaftaran
   - Bila hanya ada 1 pembayaran menunggu, ID boleh dihilangkan (cukup `ACC` atau `TOLAK alasan`).
   - Admin langsung mendapat balasan konfirmasi dari sistem. (Link halaman verifikasi tetap ada sebagai cadangan.)
4. **ACC** → halaman pelanggan otomatis lanjut ke jadwal konsultasi (pelanggan juga dapat WA).
5. **TOLAK** → pendaftaran dibatalkan, foto bukti dihapus, data dihapus dari database, pelanggan diberi tahu.

## Pemasangan di Laragon (sekali saja)
1. Letakkan folder project di `C:\laragon\www\freespace`, lalu klik **Start All** (Apache/Nginx + MySQL hijau).
2. **Database:** buka **Database (HeidiSQL)** → pilih `freespace_db` → *Berkas → Jalankan berkas SQL*:
   - database sudah berisi data → pilih `migrasi_verifikasi.sql`
   - database baru → pilih `database.sql`
3. **Konfigurasi:** di folder `api`, salin `config.example.php` → `config.php`, lalu isi `admin_password`, `secret`, dan `base_url`
   (`http://localhost/freespace` atau `http://freespace.test`, sesuai alamat yang Anda pakai di browser).
4. **Ekstensi PHP:** klik kanan ikon Laragon → *PHP → Extensions*, pastikan **curl**, **mbstring**, dan **fileinfo** tercentang (biasanya sudah aktif).
5. Buka dashboard: `http://localhost/freespace/api/admin.php` (atau `http://freespace.test/api/admin.php`).

Catatan Laragon: notifikasi/balasan WhatsApp **tidak bisa** diuji di Laragon biasa karena Fonnte tidak dapat menjangkau komputer Anda.
Gunakan dashboard `admin.php` untuk uji lokal, atau buka terowongan publik HTTPS (mis. ngrok / Cloudflare Tunnel) lalu isi `base_url` dengan alamat terowongan itu.

## Pemasangan di hosting / website online
1. **Database lama sudah berisi data?** Jalankan `migrasi_verifikasi.sql` (lewat phpMyAdmin/Adminer hosting).
   Database baru: cukup `database.sql` (sudah termasuk kolom baru).
2. Salin `api/config.example.php` → `api/config.php`, lalu isi:
   - `fonnte_token` : token perangkat dari fonnte.com (untuk WA otomatis)
   - `base_url`     : alamat website (mis. https://domainanda.com)
   - `secret`, `admin_password` : ganti dengan nilai acak/kuat
3. Pastikan folder `uploads/bukti/` bisa ditulis oleh server.
4. **Aktifkan balasan lewat WhatsApp (webhook Fonnte):**
   - Login ke `api/admin.php`, salin **URL Webhook Fonnte** yang tampil di bagian atas.
   - Di dashboard Fonnte → Device → *Webhook* → tempel URL itu pada kolom *Webhook Connect/Message*, simpan.
   - Tes: kirim `ACC` atau `TOLAK` dari nomor admin (nomor di `admin_wa`).

## Syarat agar balasan WhatsApp bekerja
- Website harus **online dengan alamat publik (HTTPS)**. Di localhost, Fonnte tidak bisa menghubungi server Anda, jadi gunakan `api/admin.php` atau link verifikasi untuk uji lokal.
- Nomor WhatsApp pengirim notifikasi (device Fonnte) harus aktif/terhubung.
- Hanya balasan dari nomor `admin_wa` yang diproses; pesan dari nomor lain diabaikan. URL webhook dilindungi kunci rahasia, jangan dibagikan.

## Dashboard admin (verifikasi data customer)
Buka `https://domainanda.com/api/admin.php` (lokal: `http://localhost/freespace/api/admin.php`), masuk dengan `admin_password`.
- Tab: **Menunggu verifikasi · Disetujui · Belum bayar · Ditolak/batal · Semua**
- Cari berdasarkan nama, ID booking, WhatsApp, atau email.
- Tiap kartu menampilkan data customer + foto bukti; untuk yang menunggu ada tombol **✅ ACC** dan **❌ Tolak** (alasan opsional).
- Simpan alamat ini sebagai bookmark.

## Catatan penting
- WhatsApp tidak punya tombol ACC/Tolak bawaan, jadi pilihannya berupa **balasan teks** (ACC / TOLAK).
- Foto bukti ikut terkirim ke WA hanya jika website sudah online (HTTPS). Di Laragon, foto dilihat lewat dashboard.
- Folder `uploads/` dilindungi `.htaccess` (hanya berlaku di Apache). Bila hosting memakai **Nginx**, tambahkan aturan yang melarang eksekusi `.php` di folder `uploads/`.
- Tanpa `fonnte_token`, WA tidak terkirim; admin tetap bisa memproses lewat `api/admin.php` (daftar antrean, pakai password).
- Jangan bagikan link verifikasi/URL webhook ke orang lain.
