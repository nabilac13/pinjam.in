<?php
function ensureEquipmentImageSchema(PDO $pdo)
{
    $column = $pdo->query("SHOW COLUMNS FROM alat LIKE 'gambar'")->fetch();
    if (!$column) {
        $pdo->exec('ALTER TABLE alat ADD COLUMN gambar VARCHAR(255) NULL AFTER deskripsi');
    }
}

function storeEquipmentImageUpload()
{
    $file = $_FILES['gambar'] ?? null;
    if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        throw new InvalidArgumentException('Gambar gagal diunggah. Pilih file gambar dan coba lagi.');
    }

    $temporaryPath = $file['tmp_name'];
    $fileSize = is_uploaded_file($temporaryPath) ? filesize($temporaryPath) : false;
    if ($fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran gambar maksimal 5 MB.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $imageInfo = getimagesize($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!$imageInfo || !isset($extensions[$mimeType]) || $imageInfo['mime'] !== $mimeType
        || $imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
        throw new InvalidArgumentException('Format gambar harus JPG, PNG, atau WebP dengan ukuran maksimal 6000 × 6000 piksel.');
    }

    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'perangkat';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan gambar tidak dapat dibuat.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Gambar tidak dapat disimpan ke folder upload.');
    }

    return 'uploads/perangkat/' . $filename;
}

function deleteEquipmentImage($imagePath)
{
    if (!is_string($imagePath)
        || !preg_match('~^uploads/perangkat/[a-f0-9]{32}\.(jpg|png|webp)$~', $imagePath)) {
        return true;
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagePath);
    return !is_file($filePath) || unlink($filePath);
}
