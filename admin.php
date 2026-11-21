<?php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
require_once __DIR__ . '/equipment_image.php';

function databaseConnection()
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'peminjaman_alat';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    ensureEquipmentImageSchema($pdo);
    return $pdo;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$noticeMessage = $_SESSION['admin_notice'] ?? '';
unset($_SESSION['admin_notice']);
$databaseError = '';
$admin = $_SESSION['admin_user'] ?? null;
$loans = [];
$equipment = [];
$users = [];
$categories = [];
$pdo = null;
$newEquipmentImage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($_SESSION['admin_csrf'], $submittedToken)) {
        http_response_code(400);
        $errorMessage = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (($_POST['action'] ?? '') === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: admin.php');
        exit;
    } elseif ($admin && in_array($_POST['action'] ?? '', ['equipment_save', 'equipment_delete', 'loan_status', 'user_status', 'password_change'], true)) {
        try {
            $pdo = databaseConnection();
            $activeStatement = $pdo->prepare(
                "SELECT id FROM users WHERE id = :id AND role = 'admin' AND status = 'aktif' LIMIT 1"
            );
            $activeStatement->execute(['id' => $admin['id']]);
            if (!$activeStatement->fetch()) {
                $_SESSION = [];
                session_regenerate_id(true);
                header('Location: admin.php');
                exit;
            }

            $action = $_POST['action'];
            if ($action === 'password_change') {
                $currentPassword = (string) ($_POST['current_password'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $confirmation = (string) ($_POST['password_confirmation'] ?? '');
                if (strlen($newPassword) < 8 || $newPassword !== $confirmation) {
                    throw new InvalidArgumentException('Password baru minimal 8 karakter dan kedua isian harus sama.');
                }
                $passwordStatement = $pdo->prepare(
                    "SELECT password_hash FROM users WHERE id = :id AND role = 'admin' AND status = 'aktif' LIMIT 1"
                );
                $passwordStatement->execute(['id' => $admin['id']]);
                $passwordHash = $passwordStatement->fetchColumn();
                if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
                    throw new InvalidArgumentException('Password saat ini tidak sesuai.');
                }
                $updatePassword = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                $updatePassword->execute([
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'id' => $admin['id'],
                ]);
                $_SESSION['admin_notice'] = 'Password admin berhasil diubah.';
                session_regenerate_id(true);
                $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
                header('Location: admin.php');
                exit;
            } elseif ($action === 'equipment_save') {
                $equipmentId = filter_var($_POST['equipment_id'] ?? '', FILTER_VALIDATE_INT);
                $categoryId = filter_var($_POST['kategori_id'] ?? '', FILTER_VALIDATE_INT);
                $code = trim((string) ($_POST['kode_alat'] ?? ''));
                $name = trim((string) ($_POST['nama'] ?? ''));
                $description = trim((string) ($_POST['deskripsi'] ?? ''));
                $stock = filter_var($_POST['stok_total'] ?? null, FILTER_VALIDATE_INT);
                $condition = $_POST['kondisi'] ?? 'baik';
                $status = $_POST['status'] ?? 'aktif';
                $previousImage = null;
                if (!$categoryId || $code === '' || strlen($code) > 30 || $name === '' || mb_strlen($name) > 120
                    || mb_strlen($description) > 2000 || $stock === false || $stock < 0
                    || !in_array($condition, ['baik', 'rusak', 'perawatan'], true)
                    || !in_array($status, ['aktif', 'nonaktif'], true)) {
                    throw new InvalidArgumentException('Data perangkat tidak valid. Periksa kembali isian formulir.');
                }

                if ($equipmentId) {
                    $imageStatement = $pdo->prepare('SELECT gambar FROM alat WHERE id = :id');
                    $imageStatement->execute(['id' => $equipmentId]);
                    $previousImage = $imageStatement->fetchColumn();
                    if ($previousImage === false) {
                        throw new InvalidArgumentException('Perangkat yang akan diubah tidak ditemukan.');
                    }
                    $reservedStatement = $pdo->prepare(
                        'SELECT stok_dipinjam FROM v_stok_alat WHERE alat_id = :id'
                    );
                    $reservedStatement->execute(['id' => $equipmentId]);
                    $reserved = $reservedStatement->fetchColumn();
                    if ($reserved === false || $stock < (int) $reserved) {
                        throw new InvalidArgumentException('Stok total tidak boleh kurang dari unit yang sedang dipinjam.');
                    }
                }

                $newEquipmentImage = storeEquipmentImageUpload();
                $imagePath = $newEquipmentImage ?: ($previousImage ?: null);
                if ($equipmentId) {
                    $statement = $pdo->prepare(
                        'UPDATE alat SET kategori_id = :kategori_id, kode_alat = :kode_alat, nama = :nama,
                         deskripsi = :deskripsi, gambar = :gambar, stok_total = :stok_total, kondisi = :kondisi, status = :status
                         WHERE id = :id'
                    );
                    $statement->execute([
                        'kategori_id' => $categoryId, 'kode_alat' => $code, 'nama' => $name,
                        'deskripsi' => $description !== '' ? $description : null, 'gambar' => $imagePath, 'stok_total' => $stock,
                        'kondisi' => $condition, 'status' => $status, 'id' => $equipmentId,
                    ]);
                } else {
                    $statement = $pdo->prepare(
                        'INSERT INTO alat (kategori_id, kode_alat, nama, deskripsi, gambar, stok_total, kondisi, status)
                         VALUES (:kategori_id, :kode_alat, :nama, :deskripsi, :gambar, :stok_total, :kondisi, :status)'
                    );
                    $statement->execute([
                        'kategori_id' => $categoryId, 'kode_alat' => $code, 'nama' => $name,
                        'deskripsi' => $description !== '' ? $description : null, 'gambar' => $imagePath, 'stok_total' => $stock,
                        'kondisi' => $condition, 'status' => $status,
                    ]);
                }
                if ($newEquipmentImage && $previousImage && !deleteEquipmentImage($previousImage)) {
                    error_log('Unable to remove replaced equipment image: ' . $previousImage);
                }
            } elseif ($action === 'equipment_delete') {
                $equipmentId = filter_var($_POST['equipment_id'] ?? '', FILTER_VALIDATE_INT);
                if (!$equipmentId) {
                    throw new InvalidArgumentException('Perangkat yang dipilih tidak valid.');
                }
                $imageStatement = $pdo->prepare('SELECT gambar FROM alat WHERE id = :id');
                $imageStatement->execute(['id' => $equipmentId]);
                $imagePath = $imageStatement->fetchColumn();
                $statement = $pdo->prepare('DELETE FROM alat WHERE id = :id');
                $statement->execute(['id' => $equipmentId]);
                if ($imagePath && !deleteEquipmentImage($imagePath)) {
                    error_log('Unable to remove deleted equipment image: ' . $imagePath);
                }
            } elseif ($action === 'loan_status') {
                $loanId = filter_var($_POST['loan_id'] ?? '', FILTER_VALIDATE_INT);
                $nextStatus = $_POST['status'] ?? '';
                if (!$loanId) {
                    throw new InvalidArgumentException('Transaksi yang dipilih tidak valid.');
                }
                $pdo->beginTransaction();
                $statement = $pdo->prepare('SELECT status FROM peminjaman WHERE id = :id FOR UPDATE');
                $statement->execute(['id' => $loanId]);
                $currentStatus = $statement->fetchColumn();
                $transitions = [
                    'menunggu_persetujuan' => ['disetujui', 'ditolak'],
                    'disetujui' => ['dipinjam'],
                    'dipinjam' => ['selesai'],
                    'menunggu_verifikasi_pengembalian' => ['dipinjam', 'selesai'],
                ];
                if (!$currentStatus || !in_array($nextStatus, $transitions[$currentStatus] ?? [], true)) {
                    $pdo->rollBack();
                    throw new InvalidArgumentException('Perubahan status peminjaman tidak diizinkan.');
                }
                $statement = $pdo->prepare(
                    "UPDATE peminjaman
                     SET status = :status,
                         disetujui_oleh = CASE WHEN :approved_status = 'disetujui' THEN :admin_id ELSE disetujui_oleh END,
                         tanggal_disetujui = CASE WHEN :approved_date = 'disetujui' THEN NOW() ELSE tanggal_disetujui END,
                         tanggal_diambil = CASE WHEN :borrowed_date = 'dipinjam' THEN NOW() ELSE tanggal_diambil END
                     WHERE id = :id"
                );
                $statement->execute([
                    'status' => $nextStatus, 'approved_status' => $nextStatus, 'admin_id' => $admin['id'],
                    'approved_date' => $nextStatus, 'borrowed_date' => $nextStatus, 'id' => $loanId,
                ]);
                if ($nextStatus === 'selesai') {
                    $returnStatement = $pdo->prepare(
                        "UPDATE pengembalian SET status = 'diterima', diverifikasi_oleh = :admin_id,
                         tanggal_verifikasi = NOW() WHERE peminjaman_id = :loan_id"
                    );
                    $returnStatement->execute(['admin_id' => $admin['id'], 'loan_id' => $loanId]);
                }
                $pdo->commit();
            } else {
                $userId = filter_var($_POST['user_id'] ?? '', FILTER_VALIDATE_INT);
                $status = $_POST['status'] ?? '';
                if (!$userId || !in_array($status, ['aktif', 'nonaktif'], true) || $userId === (int) $admin['id']) {
                    throw new InvalidArgumentException('Perubahan status akun tidak valid.');
                }
                $statement = $pdo->prepare(
                    "UPDATE users SET status = :status WHERE id = :id AND role = 'peminjam'"
                );
                $statement->execute(['status' => $status, 'id' => $userId]);
            }

            header('Location: admin.php');
            exit;
        } catch (InvalidArgumentException $exception) {
            if ($newEquipmentImage) {
                deleteEquipmentImage($newEquipmentImage);
                $newEquipmentImage = null;
            }
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($newEquipmentImage) {
                deleteEquipmentImage($newEquipmentImage);
                $newEquipmentImage = null;
            }
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin action database error: ' . $exception->getMessage());
            $errorMessage = 'Perubahan gagal disimpan. Periksa data dan pastikan perangkat belum terikat transaksi.';
        } catch (RuntimeException $exception) {
            if ($newEquipmentImage) {
                deleteEquipmentImage($newEquipmentImage);
                $newEquipmentImage = null;
            }
            error_log('Admin equipment image error: ' . $exception->getMessage());
            $errorMessage = 'Gambar tidak dapat disimpan. Periksa izin folder uploads dan coba lagi.';
        }
    }
}

if (!$admin) {
    header('Location: login.php?next=admin');
    exit;
}

if ($admin && !$databaseError) {
    try {
        $pdo = $pdo ?: databaseConnection();
        $statement = $pdo->prepare(
            "SELECT id, nama
             FROM users
             WHERE id = :id AND role = 'admin' AND status = 'aktif'
             LIMIT 1"
        );
        $statement->execute(['id' => $admin['id']]);
        $activeAdmin = $statement->fetch();

        if (!$activeAdmin) {
            $_SESSION = [];
            session_regenerate_id(true);
            header('Location: login.php?next=admin');
            exit;
        } else {
            $admin['nama'] = $activeAdmin['nama'];
            $_SESSION['admin_user']['nama'] = $activeAdmin['nama'];
            $loanStatement = $pdo->query(
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
            $loans = $loanStatement->fetchAll();

            $equipmentStatement = $pdo->query(
                "SELECT a.id, a.kode_alat, a.nama, a.deskripsi, a.gambar, a.stok_total, a.kondisi, a.status,
                        a.kategori_id, k.nama AS kategori, s.stok_dipinjam, s.stok_tersedia
                 FROM alat AS a
                 INNER JOIN kategori AS k ON k.id = a.kategori_id
                 INNER JOIN v_stok_alat AS s ON s.alat_id = a.id
                 ORDER BY a.nama"
            );
            $equipment = $equipmentStatement->fetchAll();
            $categoryStatement = $pdo->query('SELECT id, nama FROM kategori ORDER BY nama');
            $categories = $categoryStatement->fetchAll();
            $userStatement = $pdo->query(
                "SELECT id, nama, username, email, status, created_at
                 FROM users WHERE role = 'peminjam' ORDER BY created_at DESC"
            );
            $users = $userStatement->fetchAll();
        }
    } catch (PDOException $exception) {
        error_log('Admin dashboard database error: ' . $exception->getMessage());
        $databaseError = 'Data peminjaman belum dapat dimuat. Periksa koneksi database dan struktur tabel.';
    }
}

function escapeHtml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f0ede4">
    <title>Admin — Pinjam.in Perangkat IT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>window.tailwind = window.tailwind || {}; window.tailwind.config = { theme: { extend: { colors: { blue: { 50: "#eaf4f2", 100: "#d3e8e4", 200: "#a9d1c9", 300: "#7bb5aa", 400: "#4b9185", 500: "#267266", 600: "#004741", 700: "#003d38", 800: "#00332f", 900: "#002824" } } } } };</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
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
        .hover\:bg-blue-700:hover { background-color: #003d38 !important; }
        .hover\:text-blue-600:hover { color: #004741 !important; }
        .hover\:border-blue-200:hover { border-color: #a9d1c9 !important; }
        .scrollbar-hide { scrollbar-width: none; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="min-h-screen bg-[#f0ede4] text-slate-800 antialiased">
    <?php if ($databaseError): ?>
        <div role="alert" class="mx-auto mt-6 max-w-5xl rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><?= escapeHtml($databaseError) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div role="alert" class="mx-auto mt-6 max-w-5xl rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= escapeHtml($errorMessage) ?></div>
    <?php endif; ?>
    <?php if ($noticeMessage): ?>
        <div role="status" class="mx-auto mt-6 max-w-5xl rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= escapeHtml($noticeMessage) ?></div>
    <?php endif; ?>
    <div id="admin-root"></div>
    <script>
        window.adminDashboardData = <?= json_encode(
            [
                'admin' => $admin,
                'loans' => $loans,
                'equipment' => $equipment,
                'categories' => $categories,
                'users' => $users,
                'csrfToken' => $_SESSION['admin_csrf'],
                'loadError' => $databaseError,
            ],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        ) ?>;
    </script>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script type="text/babel" src="admin.jsx"></script>
</body>
</html>
