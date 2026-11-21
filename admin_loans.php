<?php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
header('Content-Type: application/json; charset=utf-8');

function respondAdminLoans($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    exit;
}

$admin = $_SESSION['admin_user'] ?? null;
if (!$admin) {
    respondAdminLoans(401, ['error' => 'Sesi admin sudah berakhir. Silakan masuk kembali.']);
}
if (!isset($admin['id']) || !filter_var($admin['id'], FILTER_VALIDATE_INT)) {
    unset($_SESSION['admin_user']);
    respondAdminLoans(401, ['error' => 'Sesi admin tidak valid. Silakan masuk kembali.']);
}

try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'peminjaman_alat';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3,
    ]);

    $activeAdmin = $pdo->prepare(
        "SELECT id FROM users WHERE id = :id AND role = 'admin' AND status = 'aktif' LIMIT 1"
    );
    $activeAdmin->execute(['id' => $admin['id']]);
    if (!$activeAdmin->fetch()) {
        unset($_SESSION['admin_user']);
        respondAdminLoans(401, ['error' => 'Akun admin tidak aktif. Silakan masuk kembali.']);
    }

    $statement = $pdo->query(
        "SELECT
            p.id,
            p.kode_peminjaman,
            u.nama AS nama_peminjam,
            u.username,
            u.email,
            p.tanggal_pengajuan,
            p.tanggal_rencana_pinjam,
            p.tanggal_rencana_kembali,
            p.status,
            COALESCE(
                GROUP_CONCAT(
                    CONCAT(a.nama, ' (', dp.jumlah, ' unit)')
                    ORDER BY a.nama
                    SEPARATOR ', '
                ),
                'Belum ada detail alat'
            ) AS daftar_alat
         FROM peminjaman AS p
         INNER JOIN users AS u ON u.id = p.peminjam_id
         LEFT JOIN detail_peminjaman AS dp ON dp.peminjaman_id = p.id
         LEFT JOIN alat AS a ON a.id = dp.alat_id
         GROUP BY
            p.id,
            p.kode_peminjaman,
            u.nama,
            u.username,
            u.email,
            p.tanggal_pengajuan,
            p.tanggal_rencana_pinjam,
            p.tanggal_rencana_kembali,
            p.status
         ORDER BY p.tanggal_pengajuan DESC"
    );
    respondAdminLoans(200, ['loans' => $statement->fetchAll()]);
} catch (Throwable $exception) {
    error_log('Admin loan refresh error: ' . $exception->getMessage());
    respondAdminLoans(500, ['error' => 'Data peminjaman gagal diperbarui. Coba muat ulang halaman.']);
}
