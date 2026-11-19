<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/verifikasi_lib.php';

function selesai(array $j): void { echo json_encode($j, JSON_UNESCAPED_UNICODE); exit; }

if (!hash_equals(webhook_key(), (string) ($_GET['k'] ?? ''))) { http_response_code(403); selesai(['ok' => false]); }

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;
$pengirim = nomor_wa((string) ($in['sender'] ?? ''));
$teks     = trim((string) ($in['message'] ?? ($in['text'] ?? '')));
$admin    = nomor_wa((string) cfg('admin_wa', ''));

if ($admin === '' || $pengirim !== $admin || $teks === '') selesai(['ok' => true, 'diproses' => false]);

if (!preg_match('/^\s*(acc|tolak)\b\s*(FS-[A-Z0-9]{1,6})?\s*(.*)$/isu', $teks, $m)) {
    selesai(['ok' => true, 'diproses' => false]);  
}
$aksi   = strtolower($m[1]);
$id     = strtoupper(trim($m[2]));
$alasan = mb_substr(trim($m[3]), 0, 250);

if ($id === '') {
    $ids = $pdo->query("SELECT id FROM booking WHERE status = 'menunggu_verifikasi'")->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) === 1) $id = $ids[0];
    else {
        wa_kirim($admin, count($ids) === 0
            ? "Tidak ada pembayaran yang menunggu verifikasi."
            : "Ada " . count($ids) . " pembayaran menunggu. Sebutkan ID-nya, contoh:\n*" . strtoupper($aksi) . " " . $ids[0] . "*\n\nMenunggu: " . implode(', ', $ids));
        selesai(['ok' => true, 'diproses' => false]);
    }
}

$h = proses_verifikasi($pdo, $id, $aksi, $alasan);
$b = $h['booking'];
switch ($h['hasil']) {
    case 'acc':
        $balas = "✅ *{$id}* di-ACC.\n{$b['nama']} · {$b['layanan_nama']}\nJadwal: {$b['tanggal']} " . substr((string) $b['jam'], 0, 5) . "\nPelanggan sudah diberi tahu dan jadwal konsultasinya aktif.";
        break;
    case 'tolak':
        $balas = "❌ *{$id}* ditolak.\n{$b['nama']} sudah diberi tahu, pendaftaran dibatalkan dan foto bukti dihapus."
               . ($alasan !== '' ? "\nAlasan: {$alasan}" : '');
        break;
    case 'sudah':
        $balas = "ℹ️ *{$id}* sudah diproses sebelumnya (status: {$b['status']}). Tidak ada perubahan.";
        break;
    default:
        $balas = "⚠️ Booking *{$id}* tidak ditemukan (mungkin sudah dihapus).";
}
wa_kirim($admin, $balas);
selesai(['ok' => true, 'diproses' => true, 'hasil' => $h['hasil'], 'id' => $id]);
