<?php
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

function respond($status, $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    exit;
}

function apiDatabaseConnection()
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'peminjaman_alat';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Metode permintaan tidak didukung.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['error' => 'Data permintaan tidak valid.']);
}

$token = $input['csrfToken'] ?? '';
if (!is_string($token) || empty($_SESSION['public_csrf']) || !hash_equals($_SESSION['public_csrf'], $token)) {
    respond(400, ['error' => 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.']);
}

$action = $input['action'] ?? '';

try {
    $pdo = apiDatabaseConnection();

    if ($action === 'register') {
        $name = trim((string) ($input['nama'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($name === '' || mb_strlen($name) > 100
            || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            || strlen($password) < 8) {
            respond(422, ['error' => 'Isi nama, username yang valid, email yang benar, dan password minimal 8 karakter.']);
        }

        $statement = $pdo->prepare(
            "INSERT INTO users (nama, username, email, password_hash, role, status)
             VALUES (:nama, :username, :email, :password_hash, 'peminjam', 'aktif')"
        );
        $statement->execute([
            'nama' => $name,
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $user = ['id' => (int) $pdo->lastInsertId(), 'nama' => $name, 'username' => $username];
        session_regenerate_id(true);
        unset($_SESSION['admin_user']);
        $_SESSION['public_user'] = $user;
        $_SESSION['public_csrf'] = bin2hex(random_bytes(32));
        respond(201, ['user' => $user, 'role' => 'peminjam', 'redirect' => 'index.php', 'csrfToken' => $_SESSION['public_csrf']]);
    }

    if ($action === 'login') {
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $statement = $pdo->prepare(
            "SELECT id, nama, username, password_hash, role
             FROM users
             WHERE username = :username AND role IN ('admin', 'peminjam') AND status = 'aktif'
             LIMIT 1"
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            respond(401, ['error' => 'Username atau password salah.']);
        }

        session_regenerate_id(true);
        $userData = [
            'id' => (int) $user['id'],
            'nama' => $user['nama'],
            'username' => $user['username'],
        ];
        if ($user['role'] === 'admin') {
            unset($_SESSION['public_user']);
            $_SESSION['admin_user'] = $userData;
        } else {
            unset($_SESSION['admin_user']);
            $_SESSION['public_user'] = $userData;
        }
        $_SESSION['public_csrf'] = bin2hex(random_bytes(32));
        respond(200, [
            'user' => $userData,
            'role' => $user['role'],
            'redirect' => $user['role'] === 'admin' ? 'admin.php' : 'index.php',
            'csrfToken' => $_SESSION['public_csrf'],
        ]);
    }

    if ($action === 'logout') {
        unset($_SESSION['public_user'], $_SESSION['admin_user']);
        session_regenerate_id(true);
        $_SESSION['public_csrf'] = bin2hex(random_bytes(32));
        respond(200, ['csrfToken' => $_SESSION['public_csrf']]);
    }

    if ($action === 'loan') {
        $user = $_SESSION['public_user'] ?? null;
        if (!$user) {
            respond(401, ['error' => 'Masuk ke akun peminjam terlebih dahulu.']);
        }
        $accountStatement = $pdo->prepare(
            "SELECT id FROM users WHERE id = :id AND role = 'peminjam' AND status = 'aktif' LIMIT 1"
        );
        $accountStatement->execute(['id' => $user['id']]);
        if (!$accountStatement->fetch()) {
            unset($_SESSION['public_user']);
            session_regenerate_id(true);
            $_SESSION['public_csrf'] = bin2hex(random_bytes(32));
            respond(401, ['error' => 'Akun peminjam tidak aktif. Silakan hubungi petugas.']);
        }

        $start = (string) ($input['tanggalPinjam'] ?? '');
        $end = (string) ($input['tanggalKembali'] ?? '');
        $note = trim((string) ($input['catatan'] ?? ''));
        $items = $input['items'] ?? null;
        $today = new DateTimeImmutable('today');
        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
        if (!$startDate || !$endDate || $startDate->format('Y-m-d') !== $start
            || $endDate->format('Y-m-d') !== $end || $startDate < $today
            || $endDate < $startDate || !is_array($items) || !$items
            || mb_strlen($note) > 2000) {
            respond(422, ['error' => 'Tanggal atau daftar perangkat tidak valid.']);
        }

        $quantities = [];
        foreach ($items as $item) {
            $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['jumlah'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || !$quantity || $quantity < 1 || $quantity > 100) {
                respond(422, ['error' => 'Jumlah perangkat tidak valid.']);
            }
            $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
        }

        $pdo->beginTransaction();
        $ids = array_keys($quantities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $lockStatement = $pdo->prepare(
            "SELECT a.id, a.nama, a.stok_total, a.status, a.kondisi
             FROM alat AS a
             WHERE a.id IN ({$placeholders})
             FOR UPDATE"
        );
        $lockStatement->execute($ids);
        $equipment = $lockStatement->fetchAll();
        if (count($equipment) !== count($ids)) {
            $pdo->rollBack();
            respond(422, ['error' => 'Ada perangkat yang sudah tidak tersedia. Muat ulang katalog.']);
        }

        $availableStatement = $pdo->prepare(
            "SELECT COALESCE(SUM(dp.jumlah), 0)
             FROM detail_peminjaman AS dp
             INNER JOIN peminjaman AS p ON p.id = dp.peminjaman_id
             WHERE dp.alat_id = :alat_id
               AND p.status IN ('disetujui', 'dipinjam', 'menunggu_verifikasi_pengembalian')"
        );
        foreach ($equipment as $device) {
            $availableStatement->execute(['alat_id' => $device['id']]);
            $reserved = (int) $availableStatement->fetchColumn();
            if ($device['status'] !== 'aktif' || $device['kondisi'] !== 'baik'
                || (int) $device['stok_total'] - $reserved < $quantities[$device['id']]) {
                $pdo->rollBack();
                respond(409, ['error' => 'Stok ' . $device['nama'] . ' tidak mencukupi.']);
            }
        }

        $code = 'PJM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $loanStatement = $pdo->prepare(
            "INSERT INTO peminjaman
                (kode_peminjaman, peminjam_id, tanggal_rencana_pinjam, tanggal_rencana_kembali, catatan_peminjam)
             VALUES (:kode, :peminjam_id, :tanggal_pinjam, :tanggal_kembali, :catatan)"
        );
        $loanStatement->execute([
            'kode' => $code,
            'peminjam_id' => $user['id'],
            'tanggal_pinjam' => $start,
            'tanggal_kembali' => $end,
            'catatan' => $note !== '' ? $note : null,
        ]);
        $loanId = (int) $pdo->lastInsertId();
        $detailStatement = $pdo->prepare(
            'INSERT INTO detail_peminjaman (peminjaman_id, alat_id, jumlah) VALUES (:peminjaman_id, :alat_id, :jumlah)'
        );
        foreach ($quantities as $id => $quantity) {
            $detailStatement->execute(['peminjaman_id' => $loanId, 'alat_id' => $id, 'jumlah' => $quantity]);
        }
        $pdo->commit();
        respond(201, ['message' => 'Pengajuan peminjaman berhasil dikirim.', 'kode' => $code]);
    }

    respond(400, ['error' => 'Aksi tidak dikenali.']);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($exception->getCode() === '23000' && $action === 'register') {
        respond(409, ['error' => 'Username atau email tersebut sudah terdaftar.']);
    }
    error_log('Public API database error: ' . $exception->getMessage());
    respond(500, ['error' => 'Permintaan gagal diproses karena masalah database.']);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Public API error: ' . $exception->getMessage());
    respond(500, ['error' => 'Terjadi kesalahan saat memproses permintaan.']);
}
