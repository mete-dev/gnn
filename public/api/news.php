<?php
require_once __DIR__ . '/config.php';

try {
    $db = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $data = getJsonInput();

    // Check for method override (e.g. from POST with _method=PUT or ID in body)
    if ($method === 'POST' && isset($_GET['_method']) && strtoupper($_GET['_method']) === 'PUT') {
        $method = 'PUT';
    }

    $id = $_GET['id'] ?? $data['id'] ?? null;
    $action = $_GET['action'] ?? null;

    if ($method === 'GET') {
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM articles WHERE id = ?");
            $stmt->execute([$id]);
            $article = $stmt->fetch();

            if (!$article) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Artikel tidak ditemukan."]);
                exit();
            }

            // Increment views
            try {
                $updateStmt = $db->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
                $updateStmt->execute([$id]);
                $article['views'] = intval($article['views']) + 1;
            } catch (Exception $e) {}

            // Fetch comments
            try {
                $cmtStmt = $db->prepare("SELECT id, user, text, time FROM comments WHERE article_id = ? ORDER BY created_at DESC");
                $cmtStmt->execute([$id]);
                $article['comments'] = $cmtStmt->fetchAll();
            } catch (Exception $e) {
                $article['comments'] = [];
            }

            // Format fields
            $bullets = json_decode($article['bullets'] ?: '[]', true);
            $article['bullets'] = is_array($bullets) ? $bullets : [];
            $article['breaking'] = (bool)$article['breaking'];
            $article['featured'] = (bool)$article['featured'];
            $article['likes'] = intval($article['likes'] ?? 0);
            $article['views'] = intval($article['views'] ?? 0);
            $article['popularRank'] = intval($article['popular_rank'] ?? 0);
            $article['subCategory'] = $article['sub_category'] ?? '';
            $article['authorEmail'] = $article['author_email'] ?? '';
            $article['timeAgo'] = $article['time_ago'] ?? 'Baru saja';
            if (empty($article['image']) || strlen(trim($article['image'])) < 5) {
                $article['image'] = 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80';
            }
            $article['videoDuration'] = $article['video_duration'] ?? '';
            $article['isPhotoGallery'] = (bool)($article['is_photo_gallery'] ?? false);
            $article['photoCount'] = intval($article['photo_count'] ?? 0);

            echo json_encode([
                "success" => true,
                "data" => $article,
                ...$article
            ]);
            exit();
        } else {
            // Safe ordering
            try {
                $stmt = $db->query("SELECT * FROM articles ORDER BY created_at DESC");
            } catch (Exception $e) {
                $stmt = $db->query("SELECT * FROM articles ORDER BY id DESC");
            }
            $articles = $stmt->fetchAll();

            foreach ($articles as &$a) {
                try {
                    $cmtStmt = $db->prepare("SELECT id, user, text, time FROM comments WHERE article_id = ? ORDER BY created_at DESC");
                    $cmtStmt->execute([$a['id']]);
                    $a['comments'] = $cmtStmt->fetchAll();
                } catch (Exception $e) {
                    $a['comments'] = [];
                }

                $bullets = json_decode($a['bullets'] ?: '[]', true);
                $a['bullets'] = is_array($bullets) ? $bullets : [];
                $a['breaking'] = (bool)$a['breaking'];
                $a['featured'] = (bool)$a['featured'];
                $a['likes'] = intval($a['likes'] ?? 0);
                $a['views'] = intval($a['views'] ?? 0);
                $a['popularRank'] = intval($a['popular_rank'] ?? 0);
                $a['subCategory'] = $a['sub_category'] ?? '';
                $a['authorEmail'] = $a['author_email'] ?? '';
                $a['timeAgo'] = $a['time_ago'] ?? 'Baru saja';
                $a['imageCredit'] = $a['image_credit'] ?? '';
                if (empty($a['image']) || strlen(trim($a['image'])) < 5) {
                    $a['image'] = 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80';
                }
                $a['videoDuration'] = $a['video_duration'] ?? '';
                $a['isPhotoGallery'] = (bool)($a['is_photo_gallery'] ?? false);
                $a['photoCount'] = intval($a['photo_count'] ?? 0);
            }

            // Filter status if query param is set
            $statusParam = $_GET['status'] ?? null;
            if ($statusParam) {
                $articles = array_values(array_filter($articles, function($item) use ($statusParam) {
                    return strtolower($item['status'] ?? '') === strtolower($statusParam);
                }));
            }

            $trendingTopics = [
                "1 USD Kembali Rp18.000",
                "Bromo Geopark Dunia",
                "Ekspor Kopi Gayo Naik 40%",
                "Kereta Cepat Tahap 2",
                "Panen Raya Padi Organik",
                "Startup Hijau Nusantara"
            ];

            $liveSchedule = [
                "08:00 - Good Morning Nusantara (Live)",
                "12:00 - Kabar Siang: Ekonomi Berdaya",
                "16:00 - Dialog Budaya & Kearifan Lokal",
                "19:00 - Good News Prime Time",
                "21:00 - Inspirasi Malam: Tokoh Bangsa"
            ];

            echo json_encode([
                "success" => true,
                "data" => $articles,
                "trendingTopics" => $trendingTopics,
                "liveSchedule" => $liveSchedule
            ]);
            exit();
        }
    }

    if ($method === 'POST') {
        if ($action === 'like') {
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID Artikel diperlukan."]);
                exit();
            }
            $stmt = $db->prepare("UPDATE articles SET likes = likes + 1 WHERE id = ?");
            $stmt->execute([$id]);

            $stmtLikes = $db->prepare("SELECT likes FROM articles WHERE id = ?");
            $stmtLikes->execute([$id]);
            $row = $stmtLikes->fetch();

            echo json_encode(["success" => true, "likes" => intval($row['likes'] ?? 0)]);
            exit();
        }

        if ($action === 'comment') {
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "ID Artikel diperlukan."]);
                exit();
            }
            $user = trim($data['user'] ?? '');
            $text = trim($data['text'] ?? '');

            if (!$user || !$text) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Nama dan komentar wajib diisi."]);
                exit();
            }

            $commentId = 'c-' . time() . '-' . rand(100, 999);
            $timeStr = 'Baru saja';

            $stmt = $db->prepare("INSERT INTO comments (id, article_id, user, text, time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$commentId, $id, $user, $text, $timeStr]);

            echo json_encode([
                "success" => true,
                "comment" => [
                    "id" => $commentId,
                    "user" => $user,
                    "text" => $text,
                    "time" => $timeStr
                ]
            ]);
            exit();
        }

        // If ID exists in database, treat as update (smart upsert)
        if ($id) {
            $checkStmt = $db->prepare("SELECT COUNT(*) as cnt FROM articles WHERE id = ?");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetch()['cnt'] > 0) {
                $bulletsJson = json_encode($data['bullets'] ?? []);
                $stmt = $db->prepare("UPDATE articles SET category=?, sub_category=?, title=?, content=?, image=?, caption=?, breaking=?, featured=?, popular_rank=?, bullets=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([
                    $data['category'] ?? 'NASIONAL',
                    $data['subCategory'] ?? '',
                    $data['title'] ?? '',
                    $data['content'] ?? '',
                    $data['image'] ?? '',
                    $data['caption'] ?? '',
                    !empty($data['breaking']) ? 1 : 0,
                    !empty($data['featured']) ? 1 : 0,
                    $data['popularRank'] ?? 0,
                    $bulletsJson,
                    $data['status'] ?? 'publish',
                    $id
                ]);

                echo json_encode(["success" => true, "message" => "Artikel berhasil diperbarui.", "data" => $data]);
                exit();
            }
        }

        // Create New Article
        $title = trim($data['title'] ?? '');
        $category = trim($data['category'] ?? 'KABAR BAIK');
        $content = trim($data['content'] ?? '');

        if (!$title || !$content) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Judul dan isi berita wajib diisi."]);
            exit();
        }

        $newId = $id ?: ('gnn-' . time() . '-' . rand(100, 999));
        $bulletsJson = json_encode($data['bullets'] ?? []);

        $stmt = $db->prepare("INSERT INTO articles (id, category, sub_category, title, content, image, caption, breaking, featured, popular_rank, bullets, date, time_ago, author, author_email, editor, location, likes, views, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $newId,
            $category,
            $data['subCategory'] ?? '',
            $title,
            $content,
            $data['image'] ?? 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80',
            $data['caption'] ?? '',
            !empty($data['breaking']) ? 1 : 0,
            !empty($data['featured']) ? 1 : 0,
            $data['popularRank'] ?? 0,
            $bulletsJson,
            $data['date'] ?? date('d M Y'),
            $data['timeAgo'] ?? 'Baru saja',
            $data['author'] ?? 'Redaksi GNN',
            $data['authorEmail'] ?? '',
            $data['editor'] ?? 'Tim Redaksi GNN',
            $data['location'] ?? 'Jakarta',
            0,
            0,
            $data['status'] ?? 'publish'
        ]);

        $data['id'] = $newId;
        $data['likes'] = 0;
        $data['views'] = 0;
        $data['comments'] = [];

        echo json_encode(["success" => true, "data" => $data]);
        exit();
    }

    if ($method === 'PUT') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID Artikel diperlukan."]);
            exit();
        }

        $bulletsJson = json_encode($data['bullets'] ?? []);
        $stmt = $db->prepare("UPDATE articles SET category=?, sub_category=?, title=?, content=?, image=?, caption=?, breaking=?, featured=?, popular_rank=?, bullets=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
        $stmt->execute([
            $data['category'] ?? 'NASIONAL',
            $data['subCategory'] ?? '',
            $data['title'] ?? '',
            $data['content'] ?? '',
            $data['image'] ?? '',
            $data['caption'] ?? '',
            !empty($data['breaking']) ? 1 : 0,
            !empty($data['featured']) ? 1 : 0,
            $data['popularRank'] ?? 0,
            $bulletsJson,
            $data['status'] ?? 'publish',
            $id
        ]);

        echo json_encode(["success" => true, "message" => "Artikel berhasil diperbarui.", "data" => $data]);
        exit();
    }

    if ($method === 'DELETE') {
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID Artikel diperlukan."]);
            exit();
        }

        $stmt = $db->prepare("DELETE FROM articles WHERE id = ?");
        $stmt->execute([$id]);

        // Also delete comments
        try {
            $delCmt = $db->prepare("DELETE FROM comments WHERE article_id = ?");
            $delCmt->execute([$id]);
        } catch (Exception $e) {}

        echo json_encode(["success" => true, "message" => "Artikel berhasil dihapus."]);
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
