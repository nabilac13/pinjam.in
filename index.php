<?php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
require_once __DIR__ . '/equipment_image.php';

if (empty($_SESSION['public_csrf'])) {
    $_SESSION['public_csrf'] = bin2hex(random_bytes(32));
}

$alat = [];
$riwayat = [];
$databaseError = '';
$user = $_SESSION['public_user'] ?? null;

try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'peminjaman_alat';
    $dbUser = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $dbUser, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    ensureEquipmentImageSchema($pdo);

    $statement = $pdo->query(
        "SELECT s.alat_id AS id, s.nama, k.nama AS kategori, s.stok_total AS stok,
                s.stok_dipinjam AS dipinjam, s.stok_tersedia AS tersedia,
                a.deskripsi, a.gambar, s.kondisi
         FROM v_stok_alat AS s
         INNER JOIN kategori AS k ON k.id = s.kategori_id
         INNER JOIN alat AS a ON a.id = s.alat_id
         WHERE s.status = 'aktif' AND s.kondisi = 'baik'
         ORDER BY k.nama, s.nama"
    );
    $colors = ['Komputer' => 'laptop', 'Jaringan' => 'network', 'Periferal' => 'peripheral', 'Elektronik' => 'projector'];
    foreach ($statement->fetchAll() as $item) {
        $item['id'] = (int) $item['id'];
        $item['stok'] = (int) $item['stok'];
        $item['dipinjam'] = (int) $item['dipinjam'];
        $item['gambar'] = $item['gambar'] ?: null;
        $item['warna'] = $colors[$item['kategori']] ?? 'laptop';
        $item['label'] = $item['tersedia'] > 0 ? 'Tersedia' : '';
        $alat[] = $item;
    }

    if ($user) {
        $historyStatement = $pdo->prepare(
            "SELECT p.id, p.kode_peminjaman, p.tanggal_rencana_pinjam, p.tanggal_rencana_kembali, p.status,
                    COALESCE(GROUP_CONCAT(CONCAT(a.nama, ' (', dp.jumlah, ')') ORDER BY a.nama SEPARATOR ', '), '') AS daftar_alat
             FROM peminjaman AS p
             LEFT JOIN detail_peminjaman AS dp ON dp.peminjaman_id = p.id
             LEFT JOIN alat AS a ON a.id = dp.alat_id
             WHERE p.peminjam_id = :user_id
             GROUP BY p.id, p.kode_peminjaman, p.tanggal_rencana_pinjam, p.tanggal_rencana_kembali, p.status
             ORDER BY p.tanggal_pengajuan DESC
             LIMIT 20"
        );
        $historyStatement->execute(['user_id' => $user['id']]);
        $riwayat = $historyStatement->fetchAll();
    }
} catch (PDOException $exception) {
    error_log('Public page database error: ' . $exception->getMessage());
    $databaseError = 'Katalog belum dapat dimuat. Pastikan database sudah diimpor dan layanan MySQL berjalan.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f0ede4">
    <title>Pinjam.in — Peminjaman Perangkat IT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>window.tailwind = window.tailwind || {}; window.tailwind.config = { theme: { extend: { colors: { blue: { 50: "#eaf4f2", 100: "#d3e8e4", 200: "#a9d1c9", 300: "#7bb5aa", 400: "#4b9185", 500: "#267266", 600: "#004741", 700: "#003d38", 800: "#00332f", 900: "#002824" } } } } };</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <style>
        :root { color-scheme: light; }
        body { font-family: "DM Sans", ui-sans-serif, system-ui, sans-serif; }
        .bg-blue-50 { background-color: #eaf4f2 !important; }
        .bg-blue-100 { background-color: #d3e8e4 !important; }
        .bg-blue-600 { background-color: #004741 !important; }
        .text-blue-50, .text-blue-100 { color: #eaf4f2 !important; }
        .text-blue-600 { color: #004741 !important; }
        .text-blue-700 { color: #003d38 !important; }
        .border-blue-200 { border-color: #a9d1c9 !important; }
        .border-blue-400 { border-color: #4b9185 !important; }
        .border-blue-600 { border-color: #004741 !important; }
        .focus\:border-blue-400:focus { border-color: #4b9185 !important; }
        .focus\:ring-blue-100:focus { --tw-ring-color: rgb(0 71 65 / .14) !important; }
        .hover\:bg-blue-700:hover, .enabled\:hover\:bg-blue-600:enabled:hover { background-color: #003d38 !important; }
        .hover\:text-blue-600:hover { color: #004741 !important; }
        .hover\:border-blue-200:hover { border-color: #a9d1c9 !important; }
        .enabled\:bg-blue-50:enabled { background-color: #eaf4f2 !important; }
        .enabled\:text-blue-700:enabled { color: #003d38 !important; }
        .enabled\:hover\:text-white:enabled:hover { color: #fff !important; }
        .soft-grid { background-image: radial-gradient(#dbe5f5 .8px, transparent .8px); background-size: 17px 17px; }
        .scrollbar-hide { scrollbar-width: none; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        dialog::backdrop { background: rgb(15 23 42 / .42); backdrop-filter: blur(3px); }
        @keyframes float { 0%, 100% { transform: translateY(0) rotate(-8deg); } 50% { transform: translateY(-9px) rotate(-4deg); } }
        .float-box { animation: float 4s ease-in-out infinite; }
    </style>
</head>
<body class="min-h-screen bg-[#f0ede4] text-slate-800 antialiased">
    <div id="root"></div>
    <script>
        window.initialEquipment = <?= json_encode($alat, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        window.publicState = <?= json_encode(
            [
                'user' => $user,
                'csrfToken' => $_SESSION['public_csrf'],
                'history' => $riwayat,
                'loadError' => $databaseError,
            ],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        ) ?>;
    </script>
    <script type="text/babel" src="app.jsx"></script>
</body>
</html>
