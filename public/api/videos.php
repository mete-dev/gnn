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
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM videos WHERE id = ?");
            $stmt->execute([$id]);
            $video = $stmt->fetch();
            if (!$video) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Video tidak ditemukan."]);
                exit();
            }
            $video['youtubeUrl'] = $video['youtube_url'] ?? '';
            $video['youtubeId'] = $video['youtube_id'] ?? '';
            $video['subCategory'] = $video['sub_category'] ?? '';
            $video['authorEmail'] = $video['author_email'] ?? '';
            echo json_encode(["success" => true, "data" => $video]);
        } else {
            // Check if table is empty, seed initial verified videos
            $countStmt = $db->query("SELECT COUNT(*) as cnt FROM videos");
            $vCount = intval($countStmt->fetch()['cnt'] ?? 0);

            if ($vCount === 0) {
                $initialVideos = [
                    [
                        'id' => 'vid-101',
                        'title' => 'Keindahan Alam Indonesia dari Udara | Wonderful Indonesia',
                        'youtube_url' => 'https://www.youtube.com/watch?v=5qap5aO4i9A',
                        'youtube_id' => '5qap5aO4i9A',
                        'description' => 'Dokumenter bentang alam nusantara Gunung Bromo, Raja Ampat, dan pantai eksotis Indonesia.',
                        'category' => 'PARIWISATA',
                        'sub_category' => 'Destinasi',
                        'author' => 'Tim Kreatif GNN',
                        'author_email' => 'reviewer@gnn.id',
                        'date' => date('d M Y'),
                        'duration' => '04:15'
                    ],
                    [
                        'id' => 'vid-102',
                        'title' => 'Inovasi Anak Bangsa: Teknologi Filter Air Bertenaga Surya',
                        'youtube_url' => 'https://www.youtube.com/watch?v=kJQP7kiw5Fk',
                        'youtube_id' => 'kJQP7kiw5Fk',
                        'description' => 'Liputan inovasi pemurni air bertenaga surya untuk desa mandiri di pelosok negeri.',
                        'category' => 'TEKNOLOGI',
                        'sub_category' => 'Inovasi',
                        'author' => 'Admin Utama GNN',
                        'author_email' => 'admin@gnn.id',
                        'date' => date('d M Y'),
                        'duration' => '05:30'
                    ],
                    [
                        'id' => 'vid-103',
                        'title' => 'Swadharma Bhakti Nagara: Kemandirian Ekonomi Kerakyatan',
                        'youtube_url' => 'https://www.youtube.com/watch?v=fJ9rUzIMcZQ',
                        'youtube_id' => 'fJ9rUzIMcZQ',
                        'description' => 'Dokumenter kebangkitan UMKM lokal menembus pasar ekspor global.',
                        'category' => 'EKONOMI',
                        'sub_category' => 'UMKM',
                        'author' => 'Bagus Wicaksono',
                        'author_email' => 'bagus@gnn.id',
                        'date' => date('d M Y'),
                        'duration' => '06:15'
                    ]
                ];

                $insStmt = $db->prepare("INSERT INTO videos (id, title, youtube_url, youtube_id, description, category, sub_category, author, author_email, date, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($initialVideos as $iv) {
                    try {
                        $insStmt->execute([
                            $iv['id'], $iv['title'], $iv['youtube_url'], $iv['youtube_id'],
                            $iv['description'], $iv['category'], $iv['sub_category'],
                            $iv['author'], $iv['author_email'], $iv['date'], $iv['duration']
                        ]);
                    } catch (Exception $e) {}
                }
            }

            try {
                $stmt = $db->query("SELECT * FROM videos ORDER BY created_at DESC");
            } catch (Exception $e) {
                $stmt = $db->query("SELECT * FROM videos ORDER BY id DESC");
            }

            $videos = $stmt->fetchAll();
            foreach ($videos as &$v) {
                $v['youtubeUrl'] = $v['youtube_url'] ?? '';
                $v['youtubeId'] = $v['youtube_id'] ?? '';
                $v['subCategory'] = $v['sub_category'] ?? '';
                $v['authorEmail'] = $v['author_email'] ?? '';
            }

            echo json_encode(["success" => true, "data" => $videos]);
        }
        exit();
    }

    if ($method === 'POST') {
        $title = trim($data['title'] ?? '');
        $youtubeUrl = trim($data['youtubeUrl'] ?? $data['youtube_url'] ?? '');

        if (!$title || !$youtubeUrl) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Judul dan URL YouTube wajib diisi."]);
            exit();
        }

        $youtubeId = $youtubeUrl;
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $youtubeUrl, $matches)) {
            $youtubeId = $matches[1];
        }

        // Check if updating existing
        if ($id) {
            $checkStmt = $db->prepare("SELECT COUNT(*) as cnt FROM videos WHERE id = ?");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetch()['cnt'] > 0) {
                $stmt = $db->prepare("UPDATE videos SET title=?, youtube_url=?, youtube_id=?, description=?, category=?, sub_category=?, author=?, author_email=?, duration=? WHERE id=?");
                $stmt->execute([
                    $title,
                    $youtubeUrl,
                    $youtubeId,
                    $data['description'] ?? '',
                    $data['category'] ?? 'EKONOMI',
                    $data['subCategory'] ?? 'Ekonomi',
                    $data['author'] ?? 'Tim Studio GNN',
                    $data['authorEmail'] ?? 'admin@gnn.id',
                    $data['duration'] ?? '03:45',
                    $id
                ]);

                $data['id'] = $id;
                $data['youtubeId'] = $youtubeId;
                echo json_encode(["success" => true, "data" => $data]);
                exit();
            }
        }

        $newId = $id ?: ('vid-' . time() . '-' . rand(100, 999));
        $stmt = $db->prepare("INSERT INTO videos (id, title, youtube_url, youtube_id, description, category, sub_category, author, author_email, date, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $newId,
            $title,
            $youtubeUrl,
            $youtubeId,
            $data['description'] ?? '',
            $data['category'] ?? 'EKONOMI',
            $data['subCategory'] ?? 'Ekonomi',
            $data['author'] ?? 'Tim Studio GNN',
            $data['authorEmail'] ?? 'admin@gnn.id',
            $data['date'] ?? date('d M Y'),
            $data['duration'] ?? '03:45'
        ]);

        $data['id'] = $newId;
        $data['youtubeId'] = $youtubeId;
        echo json_encode(["success" => true, "data" => $data]);
        exit();
    }

    if ($method === 'PUT') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID Video diperlukan."]);
            exit();
        }

        $title = trim($data['title'] ?? '');
        $youtubeUrl = trim($data['youtubeUrl'] ?? $data['youtube_url'] ?? '');
        $youtubeId = $youtubeUrl;
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $youtubeUrl, $matches)) {
            $youtubeId = $matches[1];
        }

        $stmt = $db->prepare("UPDATE videos SET title=?, youtube_url=?, youtube_id=?, description=?, category=?, sub_category=?, author=?, author_email=?, duration=? WHERE id=?");
        $stmt->execute([
            $title,
            $youtubeUrl,
            $youtubeId,
            $data['description'] ?? '',
            $data['category'] ?? 'EKONOMI',
            $data['subCategory'] ?? 'Ekonomi',
            $data['author'] ?? 'Tim Studio GNN',
            $data['authorEmail'] ?? 'admin@gnn.id',
            $data['duration'] ?? '03:45',
            $id
        ]);

        echo json_encode(["success" => true, "message" => "Video berhasil diperbarui."]);
        exit();
    }

    if ($method === 'DELETE') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID Video diperlukan."]);
            exit();
        }
        $stmt = $db->prepare("DELETE FROM videos WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["success" => true, "message" => "Video berhasil dihapus."]);
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
