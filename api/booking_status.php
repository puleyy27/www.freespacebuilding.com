<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/verifikasi_lib.php';
header('Cache-Control: no-store');

function keluar(int $kode, array $data): void
{
    http_response_code($kode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $lama = $pdo->query("SELECT id, bukti_bayar FROM booking WHERE status = 'ditolak' AND diverifikasi_pada < (NOW() - INTERVAL 3 DAY)")->fetchAll();
    foreach ($lama as $r) {
        hapus_file_bukti($r['bukti_bayar']);
        $pdo->prepare('DELETE FROM booking WHERE id = ?')->execute([$r['id']]);
    }
} catch (Throwable $e) { /* pembersihan opsional */ }

$metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$in = $metode === 'POST' ? json_decode(file_get_contents('php://input'), true) : $_GET;
$id = strtoupper(trim((string) (is_array($in) ? ($in['id'] ?? '') : '')));
if (!preg_match('/^FS-[A-Z0-9]{1,6}$/', $id)) keluar(400, ['ok' => false, 'error' => 'Booking ID tidak valid']);

$q = $pdo->prepare('SELECT status, catatan_admin, bukti_bayar FROM booking WHERE id = ?');
$q->execute([$id]);
$b = $q->fetch();
if (!$b) keluar(404, ['ok' => false, 'error' => 'Booking belum tercatat di server']);

if ($metode === 'POST') {
    if (!empty($in['ack']) && $b['status'] === 'ditolak') {
        hapus_file_bukti($b['bukti_bayar']);
        $pdo->prepare('DELETE FROM booking WHERE id = ?')->execute([$id]);
        keluar(200, ['ok' => true, 'dihapus' => true]);
    }
    keluar(200, ['ok' => true, 'dihapus' => false]);
}

keluar(200, ['ok' => true, 'status' => $b['status'], 'alasan' => $b['status'] === 'ditolak' ? (string) $b['catatan_admin'] : '']);
