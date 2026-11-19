<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/verifikasi_lib.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$sandi = (string) cfg('admin_password', '');
if (isset($_GET['keluar'])) { $_SESSION = []; session_destroy(); header('Location: admin.php'); exit; }
if (isset($_POST['sandi'])) {
    if ($sandi !== '' && hash_equals($sandi, (string) $_POST['sandi'])) { session_regenerate_id(true); $_SESSION['admin'] = 1; header('Location: admin.php'); exit; }
    sleep(1);   
    $salah = true;
}

$css = 'body{font-family:system-ui,"Segoe UI",Arial,sans-serif;background:#0b1624;color:#e8eef7;margin:0;padding:16px;line-height:1.45}'
    . '.c{max-width:880px;margin:0 auto}a{color:#6db3ff}h1{font-size:1.25rem;margin:0}.mu{color:#8fa6c4;font-size:.85rem}'
    . '.top{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}'
    . '.tabs{display:flex;gap:8px;overflow-x:auto;padding-bottom:6px;margin:10px 0}.tabs a{flex:0 0 auto;text-decoration:none;color:#e8eef7;background:#12233a;border:1px solid #1f3a5c;border-radius:99px;padding:7px 14px;font-size:.88rem}'
    . '.tabs a.on{background:#2f8cff;border-color:#2f8cff;color:#fff;font-weight:700}.tabs b{opacity:.85;margin-left:4px}'
    . '.card{background:#12233a;border:1px solid #1f3a5c;border-radius:16px;padding:16px;margin:12px 0}'
    . '.hd{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}'
    . '.tag{display:inline-block;padding:3px 11px;border-radius:99px;font-size:.74rem;font-weight:700;color:#fff}'
    . '.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:6px 18px;margin:10px 0;font-size:.9rem}.g span{color:#8fa6c4;font-size:.76rem;display:block}'
    . 'img.bk{max-width:100%;max-height:200px;border-radius:10px;margin-top:6px;background:#000;display:block}'
    . '.act{display:flex;gap:8px;flex-wrap:wrap;align-items:stretch;margin-top:12px}.act input{flex:1 1 200px;padding:10px;border-radius:10px;border:1px solid #1f3a5c;background:#0b1624;color:#e8eef7;font:inherit}'
    . 'button,.btn{border:0;border-radius:10px;padding:10px 16px;font:inherit;font-weight:700;color:#fff;cursor:pointer;background:#2f8cff;text-decoration:none}.ok{background:#16a34a}.no{background:#e5484d}'
    . '.fl{background:#0f3d2a;border:1px solid #16a34a;border-radius:12px;padding:10px 14px;margin:10px 0}.fl.x{background:#431a1c;border-color:#e5484d}'
    . 'form.s{display:flex;gap:8px;margin:8px 0}form.s input{flex:1;padding:10px;border-radius:10px;border:1px solid #1f3a5c;background:#12233a;color:#e8eef7;font:inherit}'
    . 'details{margin-top:18px}summary{cursor:pointer;color:#8fa6c4;font-size:.85rem}';
$kepala = '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Dashboard Admin - Freespace</title><style>' . $css . '</style></head><body><div class="c">';
$kaki = '</div></body></html>';

if (empty($_SESSION['admin'])) {
    echo $kepala . '<h1>Dashboard Admin</h1>'
       . ($sandi === '' ? '<p class="mu">Isi <b>admin_password</b> di api/config.php terlebih dahulu.</p>' : '')
       . (!empty($salah) ? '<p style="color:#e5484d">Kata sandi salah.</p>' : '')
       . '<form method="post" class="s"><input type="password" name="sandi" placeholder="Kata sandi admin" autofocus> <button>Masuk</button></form>' . $kaki;
    exit;
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$tab = (string) ($_REQUEST['tab'] ?? 'menunggu');
$cari = trim((string) ($_REQUEST['q'] ?? ''));
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['aksi'])) {
    $aksi = (string) $_POST['aksi'];
    $id = strtoupper(trim((string) ($_POST['id'] ?? '')));
    if (hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? '')) && in_array($aksi, ['acc', 'tolak'], true) && preg_match('/^FS-[A-Z0-9]{1,6}$/', $id)) {
        $h = proses_verifikasi($pdo, $id, $aksi, mb_substr(trim((string) ($_POST['alasan'] ?? '')), 0, 250));
        $_SESSION['flash'] = [
            ['acc' => "✅ $id di-ACC. Jadwal pelanggan aktif dan pelanggan sudah diberi tahu.", 'tolak' => "❌ $id ditolak. Pendaftaran dibatalkan, foto bukti & data dihapus.",
             'sudah' => "ℹ️ $id sudah diproses sebelumnya.", 'tidak_ada' => "⚠️ $id tidak ditemukan."][$h['hasil']],
            $h['hasil'] === 'tolak' || $h['hasil'] === 'tidak_ada',
        ];
    }
    header('Location: admin.php?tab=' . rawurlencode($tab) . ($cari !== '' ? '&q=' . rawurlencode($cari) : '')); exit;
}

$grup = [
    'menunggu' => ['Menunggu verifikasi', ['menunggu_verifikasi']],
    'disetujui' => ['Disetujui', ['dibayar', 'selesai']],
    'belum' => ['Belum bayar', ['menunggu_pembayaran']],
    'ditolak' => ['Ditolak / batal', ['ditolak', 'batal', 'kedaluwarsa']],
    'semua' => ['Semua', []],
];
if (!isset($grup[$tab])) $tab = 'menunggu';
$hit = [];
foreach ($pdo->query('SELECT status, COUNT(*) n FROM booking GROUP BY status')->fetchAll() as $r) $hit[$r['status']] = (int) $r['n'];
$jml = fn(array $st) => $st ? array_sum(array_map(fn($s) => $hit[$s] ?? 0, $st)) : array_sum($hit);

$sql = 'SELECT * FROM booking'; $par = []; $w = [];
if ($grup[$tab][1]) { $w[] = 'status IN (' . implode(',', array_fill(0, count($grup[$tab][1]), '?')) . ')'; $par = array_merge($par, $grup[$tab][1]); }
if ($cari !== '') { $w[] = '(id LIKE ? OR nama LIKE ? OR whatsapp LIKE ? OR email LIKE ?)'; $l = '%' . $cari . '%'; array_push($par, $l, $l, $l, $l); }
$sql .= ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY COALESCE(dibayar_pada, dibuat_pada) DESC LIMIT 100';
$q = $pdo->prepare($sql); $q->execute($par); $rows = $q->fetchAll();

$lab = ['menunggu_pembayaran' => ['Belum bayar', '#64748b'], 'menunggu_verifikasi' => ['Menunggu verifikasi', '#d97706'], 'dibayar' => ['Disetujui', '#16a34a'],
        'selesai' => ['Selesai', '#16a34a'], 'ditolak' => ['Ditolak', '#e5484d'], 'kedaluwarsa' => ['Kedaluwarsa', '#e5484d'], 'batal' => ['Batal', '#e5484d']];
$lokasiNama = ['studio' => 'Studio Freespace', 'online' => 'Online (Zoom/Meet)'];

echo $kepala . '<div class="top"><div><h1>Dashboard Admin</h1><div class="mu">Verifikasi data & pembayaran customer</div></div>'
   . '<div><a class="btn" style="background:#12233a;border:1px solid #1f3a5c" href="admin.php?tab=' . e($tab) . '">↻ Muat ulang</a> <a href="?keluar=1" class="mu">Keluar</a></div></div>';
if (!empty($_SESSION['flash'])) { echo '<div class="fl' . ($_SESSION['flash'][1] ? ' x' : '') . '">' . e($_SESSION['flash'][0]) . '</div>'; unset($_SESSION['flash']); }

echo '<div class="tabs">';
foreach ($grup as $k => $g) echo '<a class="' . ($k === $tab ? 'on' : '') . '" href="admin.php?tab=' . $k . ($cari !== '' ? '&q=' . rawurlencode($cari) : '') . '">' . e($g[0]) . '<b>' . $jml($g[1]) . '</b></a>';
echo '</div><form class="s" method="get"><input type="hidden" name="tab" value="' . e($tab) . '"><input name="q" value="' . e($cari) . '" placeholder="Cari nama, ID booking, WhatsApp, atau email"><button>Cari</button></form>';

if (!$rows) echo '<p class="mu">' . ($cari !== '' ? 'Tidak ada hasil untuk pencarian ini.' : 'Tidak ada data di tab ini. 🎉') . '</p>';
foreach ($rows as $b) {
    $st = $lab[$b['status']] ?? [$b['status'], '#64748b'];
    $lok = $b['lokasi_tipe'] === 'proyek' ? 'Lokasi proyek: ' . ($b['alamat_proyek'] ?: '-') : ($lokasiNama[$b['lokasi_tipe']] ?? $b['lokasi_tipe']);
    echo '<div class="card"><div class="hd"><div><b style="font-size:1.05rem">' . e($b['nama']) . '</b> <span class="mu">· ' . e($b['id']) . '</span></div><span class="tag" style="background:' . $st[1] . '">' . e($st[0]) . '</span></div><div class="g">'
       . '<div><span>WhatsApp</span><a href="https://wa.me/' . e(nomor_wa($b['whatsapp'])) . '" target="_blank" rel="noopener">' . e($b['whatsapp']) . '</a></div>'
       . '<div><span>Email</span>' . e($b['email']) . '</div>'
       . '<div><span>Layanan</span>' . e($b['layanan_nama']) . '</div>'
       . '<div><span>Jadwal</span>' . e($b['tanggal']) . ', ' . e(substr($b['jam'], 0, 5)) . '</div>'
       . '<div><span>Lokasi</span>' . e($lok) . '</div>'
       . '<div><span>Nominal</span><b>' . e(rupiah($b['biaya_reservasi'])) . '</b></div>'
       . '<div><span>Metode / pengirim</span>' . e(($b['metode_bayar'] ?: '-') . ' / ' . ($b['nama_pengirim'] ?: '-')) . '</div>'
       . '<div><span>Waktu upload bukti</span>' . e($b['dibayar_pada'] ?: '-') . '</div>'
       . (!empty($b['catatan_admin']) ? '<div><span>Alasan penolakan</span>' . e($b['catatan_admin']) . '</div>' : '')
       . '</div>';
    if ($b['bukti_bayar']) echo '<span class="mu">Foto bukti (klik untuk memperbesar):</span><a href="../' . e($b['bukti_bayar']) . '" target="_blank" rel="noopener"><img class="bk" loading="lazy" src="../' . e($b['bukti_bayar']) . '" alt="Bukti pembayaran ' . e($b['id']) . '"></a>';
    if ($b['status'] === 'menunggu_verifikasi') {
        $f = '<input type="hidden" name="id" value="' . e($b['id']) . '"><input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '"><input type="hidden" name="tab" value="' . e($tab) . '"><input type="hidden" name="q" value="' . e($cari) . '">';
        echo '<form class="act" method="post">' . $f . '<input name="alasan" maxlength="250" placeholder="Alasan tolak (opsional)">'
           . '<button class="ok" name="aksi" value="acc" type="submit" onclick="return confirm(\'ACC pembayaran ' . e($b['id']) . '? Pastikan uang sudah masuk.\')">✅ ACC</button>'
           . '<button class="no" name="aksi" value="tolak" type="submit" onclick="return confirm(\'Tolak & hapus pendaftaran ' . e($b['id']) . '?\')">❌ Tolak</button></form>';
    }
    echo '</div>';
}
echo '<details><summary>Pengaturan WhatsApp (webhook Fonnte)</summary><p class="mu" style="word-break:break-all">Tempel URL ini di pengaturan webhook Fonnte agar balasan <b>ACC</b> / <b>TOLAK</b> lewat WhatsApp bisa bekerja:<br><b>'
   . e(base_url() . '/api/wa_webhook.php?k=' . webhook_key()) . '</b></p></details>' . $kaki;
