<?php
require_once __DIR__ . '/config.php';

try {
    $db = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $data = getJsonInput();
    $id = $_GET['id'] ?? $data['id'] ?? null;

    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM gallery ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll());
        exit();
    }

    if ($method === 'POST') {
        $title = trim($data['title'] ?? '');
        $imageUrl = trim($data['imageUrl'] ?? '');

        if (!$title || !$imageUrl) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Judul dan Gambar wajib diisi."]);
            exit();
        }

        $newId = $id ?: ('gal-' . time() . '-' . rand(100, 999));
        $stmt = $db->prepare("INSERT INTO gallery (id, title, image_url, file_size_kb, caption, author, author_email, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $newId,
            $title,
            $imageUrl,
            $data['fileSizeKb'] ?? 250,
            $data['caption'] ?? '',
            $data['author'] ?? 'Pengguna',
            $data['authorEmail'] ?? '',
            date('d M Y')
        ]);

        $data['id'] = $newId;
        echo json_encode(["success" => true, "data" => $data]);
        exit();
    }

    if ($method === 'DELETE') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID Galeri diperlukan."]);
            exit();
        }
        $stmt = $db->prepare("DELETE FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["success" => true, "message" => "Item galeri berhasil dihapus."]);
        exit();
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan pada server: " . $e->getMessage()
    ]);
    exit();
}
