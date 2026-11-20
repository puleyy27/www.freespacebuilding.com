<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/verifikasi_lib.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function halaman(string $judul, string $isi): void
{
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . e($judul) . ' - Freespace Admin</title><style>'
        . 'body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#0b1624;color:#e8eef7;margin:0;padding:16px}'
        . '.c{max-width:560px;margin:0 auto;background:#12233a;border:1px solid #1f3a5c;border-radius:16px;padding:20px}'
        . 'h1{font-size:1.15rem;margin:0 0 4px}.mu{color:#8fa6c4;font-size:.85rem}'
        . '.kv{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid #1f3a5c;font-size:.92rem}'
        . '.kv span:first-child{color:#8fa6c4;flex:0 0 38%}.kv span:last-child{text-align:right;word-break:break-word}'
        . 'img{max-width:100%;border-radius:12px;margin-top:10px;background:#000}'
        . '.b{display:block;width:100%;border:0;border-radius:12px;padding:14px;font-size:1rem;font-weight:700;color:#fff;cursor:pointer;margin-top:10px}'
        . '.ok{background:#16a34a}.no{background:#e5484d}.tag{display:inline-block;padding:4px 12px;border-radius:99px;font-size:.78rem;font-weight:700;color:#fff}'
        . 'textarea{width:100%;box-sizing:border-box;border-radius:10px;border:1px solid #1f3a5c;background:#0b1624;color:#e8eef7;padding:10px;margin-top:8px;font:inherit}'
        . '</style></head><body><div class="c">' . $isi . '</div></body></html>';
    exit;
}

$id = strtoupper(trim((string) ($_REQUEST['id'] ?? '')));
$t  = (string) ($_REQUEST['t'] ?? '');
if (!preg_match('/^FS-[A-Z0-9]{1,6}$/', $id) || !token_valid($id, $t)) {
    http_response_code(403);
    halaman('Link tidak valid', '<h1>Link tidak valid</h1><p class="mu">Link verifikasi salah atau sudah tidak berlaku.</p>');
}

$q = $pdo->prepare('SELECT * FROM booking WHERE id = ?');
$q->execute([$id]);
$b = $q->fetch();
if (!$b) {
    halaman('Tidak ditemukan', '<h1>Booking tidak ditemukan</h1><p class="mu">Pendaftaran <b>' . e($id) . '</b> sudah dihapus atau tidak ada.</p>');
}

$pesan = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $aksi   = (string) ($_POST['aksi'] ?? '');
    $alasan = mb_substr(trim((string) ($_POST['alasan'] ?? '')), 0, 250);
    if (!in_array($aksi, ['acc', 'tolak'], true)) { http_response_code(400); halaman('Aksi salah', '<h1>Aksi tidak dikenal</h1>'); }

    $hasil = proses_verifikasi($pdo, $id, $aksi, $alasan);
    if ($hasil['hasil'] === 'acc') {
        halaman('Pembayaran di-ACC', '<h1>✅ Pembayaran di-ACC</h1><p class="mu">Booking <b>' . e($id) . '</b> milik ' . e($hasil['booking']['nama']) . ' sudah terverifikasi. Jadwal konsultasi pelanggan otomatis aktif.</p>');
    }
    if ($hasil['hasil'] === 'tolak') {
        halaman('Pembayaran ditolak', '<h1>❌ Pembayaran ditolak</h1><p class="mu">Booking <b>' . e($id) . '</b> dibatalkan dan foto bukti dihapus. Pelanggan sudah diberi tahu.</p>');
    }
    if ($hasil['hasil'] === 'tidak_ada') halaman('Tidak ditemukan', '<h1>Booking tidak ditemukan</h1><p class="mu">Pendaftaran sudah dihapus.</p>');
    $b = $hasil['booking'];
    $pesan = '<p class="mu">Booking ini sudah diproses sebelumnya.</p>';
}

$lokasi = ['studio' => 'Studio Freespace', 'proyek' => 'Lokasi proyek: ' . ($b['alamat_proyek'] ?? '-'), 'online' => 'Online (Zoom/Meet)'][$b['lokasi_tipe']] ?? $b['lokasi_tipe'];
$label  = ['menunggu_pembayaran' => ['Belum bayar', '#d97706'], 'menunggu_verifikasi' => ['Menunggu verifikasi', '#d97706'], 'dibayar' => ['Sudah di-ACC', '#16a34a'],
           'selesai' => ['Selesai', '#16a34a'], 'ditolak' => ['Ditolak', '#e5484d'], 'kedaluwarsa' => ['Kedaluwarsa', '#e5484d'], 'batal' => ['Batal', '#e5484d']][$b['status']] ?? [$b['status'], '#64748b'];

$html = '<h1>Verifikasi Pembayaran</h1><p class="mu">Booking ' . e($b['id']) . '</p>'
    . '<p><span class="tag" style="background:' . $label[1] . '">' . e($label[0]) . '</span></p>' . $pesan
    . '<div class="kv"><span>Nama</span><span>' . e($b['nama']) . '</span></div>'
    . '<div class="kv"><span>WhatsApp</span><span><a style="color:#6db3ff" href="https://wa.me/' . e(nomor_wa($b['whatsapp'])) . '">' . e($b['whatsapp']) . '</a></span></div>'
    . '<div class="kv"><span>Email</span><span>' . e($b['email']) . '</span></div>'
    . '<div class="kv"><span>Layanan</span><span>' . e($b['layanan_nama']) . '</span></div>'
    . '<div class="kv"><span>Jadwal</span><span>' . e($b['tanggal']) . ', ' . e(substr($b['jam'], 0, 5)) . '</span></div>'
    . '<div class="kv"><span>Lokasi</span><span>' . e($lokasi) . '</span></div>'
    . '<div class="kv"><span>Nominal</span><span><b>' . e(rupiah($b['biaya_reservasi'])) . '</b></span></div>'
    . '<div class="kv"><span>Metode</span><span>' . e($b['metode_bayar'] ?? '-') . '</span></div>'
    . '<div class="kv"><span>Nama pengirim</span><span>' . e($b['nama_pengirim'] ?? '-') . '</span></div>'
    . '<div class="kv"><span>Waktu upload</span><span>' . e($b['dibayar_pada'] ?? '-') . '</span></div>';

if ($b['bukti_bayar']) {
    $html .= '<p class="mu" style="margin-bottom:0">Foto bukti transfer:</p><a href="../' . e($b['bukti_bayar']) . '" target="_blank" rel="noopener"><img src="../' . e($b['bukti_bayar']) . '" alt="Bukti pembayaran"></a>';
} else {
    $html .= '<p class="mu">Foto bukti tidak tersedia.</p>';
}

if ($b['status'] === 'menunggu_verifikasi') {
    $pilih = (string) ($_GET['aksi'] ?? '');
    $hid = '<input type="hidden" name="id" value="' . e($id) . '"><input type="hidden" name="t" value="' . e($t) . '">';
    $btnAcc = '<button class="b ok" name="aksi" value="acc" type="submit">✅ ACC - Pembayaran Valid</button>';
    $btnTolak = '<textarea name="alasan" rows="2" maxlength="250" placeholder="Alasan penolakan (opsional, dikirim ke pelanggan)"></textarea>'
        . '<button class="b no" name="aksi" value="tolak" type="submit" onclick="return confirm(\'Tolak pembayaran? Pendaftaran akan dibatalkan dan dihapus.\')">❌ TOLAK - Batalkan Pendaftaran</button>';

    $html .= '<p class="mu">Cek mutasi rekening, pastikan nominal & nama pengirim sesuai dan uang sudah masuk sebelum ACC.</p>'
        . '<form method="post">' . $hid
        . ($pilih === 'acc' ? $btnAcc : ($pilih === 'tolak' ? $btnTolak : $btnAcc . $btnTolak))
        . '</form>';
}
halaman('Verifikasi ' . $b['id'], $html);
