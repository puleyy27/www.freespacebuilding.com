<?php

function cfg(string $kunci, $bawaan = '')
{
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/config.php';
        $c = is_file($f) ? (require $f) : [];
        if (!is_array($c)) $c = [];
    }
    return $c[$kunci] ?? $bawaan;
}

function base_url(): string
{
    return rtrim((string) cfg('base_url', 'http://localhost/freespace'), '/');
}

function token_verif(string $id): string
{
    return substr(hash_hmac('sha256', 'verif|' . $id, (string) cfg('secret', 'dev-secret-ganti-di-config')), 0, 32);
}

function token_valid(string $id, string $t): bool
{
    return $t !== '' && hash_equals(token_verif($id), $t);
}

function link_verif(string $id): string
{
    return base_url() . '/api/verifikasi.php?id=' . rawurlencode($id) . '&t=' . token_verif($id);
}

function rupiah($n): string
{
    return 'Rp ' . number_format((int) $n, 0, ',', '.');
}

function nomor_wa(string $no): string
{
    $no = preg_replace('/\D+/', '', $no);
    if (strpos($no, '0') === 0) $no = '62' . substr($no, 1);
    return $no;
}

function wa_kirim(string $target, string $pesan, ?string $gambarUrl = null): bool
{
    $token = (string) cfg('fonnte_token', '');
    if ($token === '' || !function_exists('curl_init')) {
        error_log('[Freespace] WA tidak dikirim (fonnte_token kosong / cURL tidak aktif).');
        return false;
    }
    $data = ['target' => nomor_wa($target), 'message' => $pesan, 'countryCode' => '62'];
    if ($gambarUrl && preg_match('#^https://#i', $gambarUrl)) $data['url'] = $gambarUrl;

    $ch = curl_init('https://api.fonnte.com/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) { error_log('[Freespace] WA gagal: ' . $err); return false; }
    $j = json_decode((string) $res, true);
    $ok = is_array($j) && !empty($j['status']);
    if (!$ok) error_log('[Freespace] WA ditolak Fonnte: ' . $res);
    return $ok;
}

function url_bukti(?string $path): ?string
{
    return $path ? base_url() . '/' . ltrim($path, '/') : null;
}

function notif_admin(array $b): bool
{
    $admin = (string) cfg('admin_wa', '');
    if ($admin === '') return false;

    $lokasi = ['studio' => 'Studio Freespace', 'proyek' => 'Lokasi proyek: ' . ($b['alamat_proyek'] ?? '-'), 'online' => 'Online (Zoom/Meet)'][$b['lokasi_tipe']] ?? $b['lokasi_tipe'];
    $bukti  = url_bukti($b['bukti_bayar'] ?? null);

    $pesan = "🔔 *PEMBAYARAN BARU - PERLU VERIFIKASI*\n\n"
        . "Booking ID : *{$b['id']}*\n"
        . "Nama : {$b['nama']}\n"
        . "WhatsApp : {$b['whatsapp']}\n"
        . "Email : {$b['email']}\n"
        . "Layanan : {$b['layanan_nama']}\n"
        . "Jadwal : {$b['tanggal']} pukul " . substr((string) $b['jam'], 0, 5) . "\n"
        . "Lokasi : {$lokasi}\n\n"
        . "Nominal : *" . rupiah($b['biaya_reservasi']) . "*\n"
        . "Metode : " . ($b['metode_bayar'] ?? '-') . "\n"
        . "Pengirim : " . ($b['nama_pengirim'] ?? '-') . "\n"
        . ($bukti ? "Foto bukti : {$bukti}\n" : '')
        . "\nPastikan uang sudah masuk ke rekening, lalu *balas pesan ini*:\n"
        . "✅ *ACC {$b['id']}*  → pembayaran valid\n"
        . "❌ *TOLAK {$b['id']} alasan*  → batalkan & hapus pendaftaran\n\n"
        . "(Atau buka halaman verifikasi: " . link_verif($b['id']) . ")";

    return wa_kirim($admin, $pesan, $bukti);
}

function notif_pelanggan(array $b, bool $diterima, string $alasan = ''): bool
{
    if (empty($b['whatsapp'])) return false;
    if ($diterima) {
        $pesan = "Halo {$b['nama']}, pembayaran reservasi Anda (*{$b['id']}*) sudah *DIVERIFIKASI* ✅\n\n"
            . "Jadwal konsultasi: {$b['tanggal']} pukul " . substr((string) $b['jam'], 0, 5) . "\n"
            . "Layanan: {$b['layanan_nama']}\n\nSampai jumpa! - Freespace Building";
    } else {
        $pesan = "Halo {$b['nama']}, mohon maaf, pembayaran reservasi *{$b['id']}* belum dapat kami verifikasi ❌"
            . ($alasan !== '' ? "\nAlasan: {$alasan}" : '')
            . "\n\nPendaftaran dibatalkan. Jika Anda sudah transfer, silakan hubungi kami dan buat reservasi baru dengan bukti yang sesuai. - Freespace Building";
    }
    return wa_kirim($b['whatsapp'], $pesan);
}

function hapus_file_bukti(?string $path): void
{
    if (!$path) return;
    $full = realpath(dirname(__DIR__) . '/' . $path);
    $root = realpath(dirname(__DIR__) . '/uploads/bukti');
    if ($full && $root && strpos($full, $root . DIRECTORY_SEPARATOR) === 0 && is_file($full)) @unlink($full);
}

function proses_verifikasi(PDO $pdo, string $id, string $aksi, string $alasan = ''): array
{
    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT * FROM booking WHERE id = ? FOR UPDATE');
    $q->execute([$id]);
    $b = $q->fetch();
    if (!$b) { $pdo->rollBack(); return ['hasil' => 'tidak_ada', 'booking' => null]; }
    if ($b['status'] !== 'menunggu_verifikasi') { $pdo->rollBack(); return ['hasil' => 'sudah', 'booking' => $b]; }

    if ($aksi === 'acc') {
        $pdo->prepare("UPDATE booking SET status = 'dibayar', diverifikasi_pada = NOW(), catatan_admin = NULL WHERE id = ?")->execute([$id]);
        $pdo->commit();
        notif_pelanggan($b, true);
        return ['hasil' => 'acc', 'booking' => $b];
    }
    hapus_file_bukti($b['bukti_bayar']);
    $pdo->prepare("UPDATE booking SET status = 'ditolak', bukti_bayar = NULL, diverifikasi_pada = NOW(), catatan_admin = ? WHERE id = ?")
        ->execute([$alasan !== '' ? $alasan : null, $id]);
    $pdo->commit();
    notif_pelanggan($b, false, $alasan);
    return ['hasil' => 'tolak', 'booking' => $b];
}

function webhook_key(): string
{
    $k = (string) cfg('webhook_key', '');
    return $k !== '' ? $k : substr(hash_hmac('sha256', 'webhook', (string) cfg('secret', 'dev-secret-ganti-di-config')), 0, 24);
}
