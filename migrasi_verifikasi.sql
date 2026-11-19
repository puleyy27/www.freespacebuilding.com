USE freespace_db;

ALTER TABLE booking
  MODIFY status ENUM('menunggu_pembayaran','menunggu_verifikasi','dibayar','selesai','batal','kedaluwarsa','ditolak')
    NOT NULL DEFAULT 'menunggu_pembayaran',
  ADD COLUMN diverifikasi_pada DATETIME NULL COMMENT 'Waktu admin ACC / tolak pembayaran' AFTER dibayar_pada,
  ADD COLUMN catatan_admin VARCHAR(255) NULL COMMENT 'Alasan penolakan (opsional)' AFTER diverifikasi_pada;

-- Booking lama yang sudah terlanjur "dibayar" sebelum fitur ini dianggap terverifikasi.
-- (Hapus baris ini bila ingin admin memeriksa ulang semuanya.)
UPDATE booking SET status = 'dibayar', diverifikasi_pada = dibayar_pada
 WHERE status = 'menunggu_verifikasi' AND selesai_pada IS NOT NULL;
