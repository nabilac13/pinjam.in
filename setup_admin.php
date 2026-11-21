<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'peminjaman_alat';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPassword = getenv('DB_PASSWORD') ?: '';
$pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $dbUser, $dbPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$username = 'admin_it';
$existingStatement = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
$existingStatement->execute(['username' => $username]);
if ($existingStatement->fetch()) {
    fwrite(STDERR, "Username admin_it sudah digunakan. Tidak ada akun baru yang dibuat.\n");
    exit(1);
}

$temporaryPassword = rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
$statement = $pdo->prepare(
    "INSERT INTO users (nama, username, password_hash, role, status)
     VALUES ('Administrator', :username, :password_hash, 'admin', 'aktif')"
);
$statement->execute([
    'username' => $username,
    'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
]);

fwrite(STDOUT, "Akun admin berhasil dibuat. Simpan kredensial ini sekarang:\n");
fwrite(STDOUT, "Username: {$username}\n");
fwrite(STDOUT, "Password sementara: {$temporaryPassword}\n");
fwrite(STDOUT, "Password tidak akan ditampilkan ulang.\n");
