<?php
require_once __DIR__ . '/config.php';

try {
    $db = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $data = getJsonInput();

    if ($method === 'POST' && isset($_GET['_method']) && strtoupper($_GET['_method']) === 'PUT') {
        $method = 'PUT';
    }

    $id = $_GET['id'] ?? $data['id'] ?? null;

    if ($method === 'GET') {
        $stmt = $db->query("SELECT id, username, email, name, role, avatar, status, created_at FROM users ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll());
        exit();
    }

    if ($method === 'POST') {
        $email = strtolower(trim($data['email'] ?? ''));
        $username = strtolower(trim($data['username'] ?? ''));

        if (!$email || !$username) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Email dan Username wajib diisi."]);
            exit();
        }

        $newId = $id ?: ('usr-' . time() . '-' . rand(100, 999));
        $stmt = $db->prepare("INSERT INTO users (id, username, email, name, role, password, avatar, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $newId,
            $username,
            $email,
            $data['name'] ?? $username,
            $data['role'] ?? 'sahabat',
            $data['password'] ?? '123456',
            $data['avatar'] ?? '',
            $data['status'] ?? 'active'
        ]);

        echo json_encode(["success" => true, "data" => ["id" => $newId, "username" => $username, "email" => $email]]);
        exit();
    }

    if ($method === 'PUT') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID Pengguna diperlukan."]);
            exit();
        }
        $stmt = $db->prepare("UPDATE users SET name=?, role=?, status=? WHERE id=?");
        $stmt->execute([
            $data['name'] ?? '',
            $data['role'] ?? 'sahabat',
            $data['status'] ?? 'active',
            $id
        ]);

        echo json_encode(["success" => true, "message" => "Pengguna berhasil diperbarui."]);
        exit();
    }

    if ($method === 'DELETE') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID Pengguna diperlukan."]);
            exit();
        }
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["success" => true, "message" => "Pengguna berhasil dihapus."]);
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
