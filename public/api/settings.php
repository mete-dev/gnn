<?php
require_once __DIR__ . '/config.php';

try {
    $db = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'backup';
    $format = strtolower($_GET['format'] ?? 'json');

    if ($method === 'GET' || $action === 'backup') {
        // 1. Fetch Articles with Comments
        $stmtArt = $db->query("SELECT * FROM articles ORDER BY created_at DESC");
        $articles = $stmtArt->fetchAll();
        foreach ($articles as &$a) {
            $cmtStmt = $db->prepare("SELECT id, user, text, time FROM comments WHERE article_id = ? ORDER BY created_at DESC");
            $cmtStmt->execute([$a['id']]);
            $a['comments'] = $cmtStmt->fetchAll();
            $a['bullets'] = json_decode($a['bullets'] ?: '[]', true);
            $a['breaking'] = (bool)$a['breaking'];
            $a['featured'] = (bool)$a['featured'];
            $a['likes'] = intval($a['likes']);
            $a['views'] = intval($a['views']);
        }

        // 2. Fetch Users
        $stmtUsr = $db->query("SELECT id, username, email, name, role, avatar, status, created_at FROM users ORDER BY created_at DESC");
        $users = $stmtUsr->fetchAll();

        // 3. Fetch Gallery
        $stmtGal = $db->query("SELECT * FROM gallery ORDER BY created_at DESC");
        $gallery = $stmtGal->fetchAll();

        // 4. Fetch Videos
        $stmtVid = $db->query("SELECT * FROM videos ORDER BY created_at DESC");
        $videos = $stmtVid->fetchAll();

        // SQL Format Dump (Directly importable into phpMyAdmin / MySQL)
        if ($format === 'sql') {
            header('Content-Type: application/sql; charset=UTF-8');
            header('Content-Disposition: attachment; filename="gnn_mysql_backup_' . date('Y-m-d_His') . '.sql"');

            echo "-- Good News Nusantara Database MySQL Backup\n";
            echo "-- Exported At: " . date('Y-m-d H:i:s') . "\n\n";
            echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

            // Dump Users
            if (!empty($users)) {
                echo "-- Table: users\n";
                foreach ($users as $u) {
                    $uId = $db->quote($u['id']);
                    $uUser = $db->quote($u['username']);
                    $uEmail = $db->quote($u['email']);
                    $uName = $db->quote($u['name']);
                    $uRole = $db->quote($u['role']);
                    $uPass = $db->quote($u['password'] ?? 'a@3etbwsye3');
                    $uAvatar = $db->quote($u['avatar'] ?? '');
                    $uStatus = $db->quote($u['status'] ?? 'active');
                    echo "INSERT INTO `users` (`id`, `username`, `email`, `name`, `role`, `password`, `avatar`, `status`) VALUES ($uId, $uUser, $uEmail, $uName, $uRole, $uPass, $uAvatar, $uStatus) ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), password=VALUES(password);\n";
                }
                echo "\n";
            }

            // Dump Articles & Comments
            if (!empty($articles)) {
                echo "-- Table: articles\n";
                foreach ($articles as $a) {
                    $aId = $db->quote($a['id']);
                    $aCat = $db->quote($a['category'] ?? 'KABAR BAIK');
                    $aSubCat = $db->quote($a['sub_category'] ?? '');
                    $aTitle = $db->quote($a['title']);
                    $aContent = $db->quote($a['content']);
                    $aImage = $db->quote($a['image']);
                    $aCaption = $db->quote($a['caption'] ?? '');
                    $aBreaking = !empty($a['breaking']) ? 1 : 0;
                    $aFeatured = !empty($a['featured']) ? 1 : 0;
                    $aRank = intval($a['popular_rank'] ?? 0);
                    $aBullets = $db->quote(json_encode($a['bullets'] ?? []));
                    $aDate = $db->quote($a['date'] ?? date('d M Y'));
                    $aTimeAgo = $db->quote($a['time_ago'] ?? 'Baru saja');
                    $aAuthor = $db->quote($a['author'] ?? 'Redaksi GNN');
                    $aAuthorEmail = $db->quote($a['author_email'] ?? '');
                    $aEditor = $db->quote($a['editor'] ?? 'Tim Redaksi GNN');
                    $aLocation = $db->quote($a['location'] ?? 'Jakarta');
                    $aLikes = intval($a['likes'] ?? 0);
                    $aViews = intval($a['views'] ?? 0);
                    $aStatus = $db->quote($a['status'] ?? 'publish');

                    echo "INSERT INTO `articles` (`id`, `category`, `sub_category`, `title`, `content`, `image`, `caption`, `breaking`, `featured`, `popular_rank`, `bullets`, `date`, `time_ago`, `author`, `author_email`, `editor`, `location`, `likes`, `views`, `status`) VALUES ($aId, $aCat, $aSubCat, $aTitle, $aContent, $aImage, $aCaption, $aBreaking, $aFeatured, $aRank, $aBullets, $aDate, $aTimeAgo, $aAuthor, $aAuthorEmail, $aEditor, $aLocation, $aLikes, $aViews, $aStatus) ON DUPLICATE KEY UPDATE title=VALUES(title), content=VALUES(content), image=VALUES(image);\n";

                    if (!empty($a['comments'])) {
                        foreach ($a['comments'] as $c) {
                            $cId = $db->quote($c['id']);
                            $cUser = $db->quote($c['user']);
                            $cText = $db->quote($c['text']);
                            $cTime = $db->quote($c['time'] ?? 'Baru saja');
                            echo "INSERT IGNORE INTO `comments` (`id`, `article_id`, `user`, `text`, `time`) VALUES ($cId, $aId, $cUser, $cText, $cTime);\n";
                        }
                    }
                }
                echo "\n";
            }

            // Dump Gallery
            if (!empty($gallery)) {
                echo "-- Table: gallery\n";
                foreach ($gallery as $g) {
                    $gId = $db->quote($g['id']);
                    $gTitle = $db->quote($g['title']);
                    $gImg = $db->quote($g['image_url']);
                    $gSize = intval($g['file_size_kb'] ?? 250);
                    $gCap = $db->quote($g['caption'] ?? '');
                    $gAuth = $db->quote($g['author'] ?? 'Pengguna');
                    $gAuthEmail = $db->quote($g['author_email'] ?? '');
                    $gDate = $db->quote($g['date'] ?? date('d M Y'));
                    echo "INSERT INTO `gallery` (`id`, `title`, `image_url`, `file_size_kb`, `caption`, `author`, `author_email`, `date`) VALUES ($gId, $gTitle, $gImg, $gSize, $gCap, $gAuth, $gAuthEmail, $gDate) ON DUPLICATE KEY UPDATE title=VALUES(title), image_url=VALUES(image_url);\n";
                }
                echo "\n";
            }

            // Dump Videos
            if (!empty($videos)) {
                echo "-- Table: videos\n";
                foreach ($videos as $v) {
                    $vId = $db->quote($v['id']);
                    $vTitle = $db->quote($v['title']);
                    $vUrl = $db->quote($v['youtube_url']);
                    $vYtId = $db->quote($v['youtube_id']);
                    $vDesc = $db->quote($v['description'] ?? '');
                    $vCat = $db->quote($v['category'] ?? 'EKONOMI');
                    $vSubCat = $db->quote($v['sub_category'] ?? 'Ekonomi');
                    $vAuth = $db->quote($v['author'] ?? 'Tim Studio GNN');
                    $vAuthEmail = $db->quote($v['author_email'] ?? 'admin@gnn.id');
                    $vDate = $db->quote($v['date'] ?? date('d M Y'));
                    $vDur = $db->quote($v['duration'] ?? '03:45');
                    echo "INSERT INTO `videos` (`id`, `title`, `youtube_url`, `youtube_id`, `description`, `category`, `sub_category`, `author`, `author_email`, `date`, `duration`) VALUES ($vId, $vTitle, $vUrl, $vYtId, $vDesc, $vCat, $vSubCat, $vAuth, $vAuthEmail, $vDate, $vDur) ON DUPLICATE KEY UPDATE title=VALUES(title), youtube_url=VALUES(youtube_url);\n";
                }
                echo "\n";
            }

            echo "SET FOREIGN_KEY_CHECKS=1;\n";
            exit();
        }

        // Default: JSON Format Backup
        $backupData = [
            "appName" => "Good News Nusantara Studio",
            "exportedAt" => date('c'),
            "version" => "1.0.0",
            "data" => [
                "articles" => $articles,
                "users" => $users,
                "gallery" => $gallery,
                "videos" => $videos
            ]
        ];

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="gnn_studio_backup_' . date('Y-m-d_His') . '.json"');
        echo json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    if ($method === 'POST' || $action === 'restore') {
        $input = getJsonInput();
        $data = $input['data'] ?? $input;
        $overwrite = !empty($input['overwrite']);

        if (!$data || (!isset($data['articles']) && !isset($data['users']) && !isset($data['gallery']) && !isset($data['videos']))) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Format file backup tidak valid."]);
            exit();
        }

        // Restore Articles
        if (!empty($data['articles']) && is_array($data['articles'])) {
            if ($overwrite) {
                $db->exec("DELETE FROM comments");
                $db->exec("DELETE FROM articles");
            }

            $insArt = $db->prepare("INSERT INTO articles (id, category, sub_category, title, content, image, caption, breaking, featured, popular_rank, bullets, date, time_ago, author, author_email, editor, location, likes, views, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE category=VALUES(category), sub_category=VALUES(sub_category), title=VALUES(title), content=VALUES(content), image=VALUES(image), caption=VALUES(caption), breaking=VALUES(breaking), featured=VALUES(featured), popular_rank=VALUES(popular_rank), bullets=VALUES(bullets), status=VALUES(status)");

            $insCmt = $db->prepare("INSERT IGNORE INTO comments (id, article_id, user, text, time) VALUES (?, ?, ?, ?, ?)");

            foreach ($data['articles'] as $a) {
                $bulletsJson = is_array($a['bullets'] ?? null) ? json_encode($a['bullets']) : json_encode([]);
                $artId = $a['id'] ?? ('gnn-' . time() . '-' . rand(100, 999));

                $insArt->execute([
                    $artId,
                    $a['category'] ?? 'KABAR BAIK',
                    $a['subCategory'] ?? '',
                    $a['title'] ?? '',
                    $a['content'] ?? '',
                    $a['image'] ?? '',
                    $a['caption'] ?? '',
                    !empty($a['breaking']) ? 1 : 0,
                    !empty($a['featured']) ? 1 : 0,
                    $a['popularRank'] ?? 0,
                    $bulletsJson,
                    $a['date'] ?? date('d M Y'),
                    $a['timeAgo'] ?? 'Baru saja',
                    $a['author'] ?? 'Redaksi GNN',
                    $a['authorEmail'] ?? '',
                    $a['editor'] ?? 'Tim Redaksi GNN',
                    $a['location'] ?? 'Jakarta',
                    $a['likes'] ?? 0,
                    $a['views'] ?? 0,
                    $a['status'] ?? 'publish'
                ]);

                if (!empty($a['comments']) && is_array($a['comments'])) {
                    foreach ($a['comments'] as $c) {
                        $cId = $c['id'] ?? ('c-' . time() . '-' . rand(100, 999));
                        $insCmt->execute([$cId, $artId, $c['user'] ?? 'Pembaca', $c['text'] ?? '', $c['time'] ?? 'Baru saja']);
                    }
                }
            }
        }

        // Restore Users
        if (!empty($data['users']) && is_array($data['users'])) {
            if ($overwrite) {
                $db->exec("DELETE FROM users WHERE role != 'admin'");
            }

            $insUsr = $db->prepare("INSERT INTO users (id, username, email, name, role, avatar, status) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role), avatar=VALUES(avatar), status=VALUES(status)");

            foreach ($data['users'] as $u) {
                $uId = $u['id'] ?? ('usr-' . time() . '-' . rand(100, 999));
                $insUsr->execute([
                    $uId,
                    $u['username'] ?? ('user' . rand(100, 999)),
                    $u['email'] ?? ($uId . '@gnn.id'),
                    $u['name'] ?? 'Pengguna GNN',
                    $u['role'] ?? 'sahabat',
                    $u['avatar'] ?? '',
                    $u['status'] ?? 'active'
                ]);
            }
        }

        // Restore Gallery
        if (!empty($data['gallery']) && is_array($data['gallery'])) {
            if ($overwrite) {
                $db->exec("DELETE FROM gallery");
            }

            $insGal = $db->prepare("INSERT INTO gallery (id, title, image_url, file_size_kb, caption, author, author_email, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), image_url=VALUES(image_url), caption=VALUES(caption)");

            foreach ($data['gallery'] as $g) {
                $gId = $g['id'] ?? ('gal-' . time() . '-' . rand(100, 999));
                $insGal->execute([
                    $gId,
                    $g['title'] ?? 'Foto Dokumentasi',
                    $g['imageUrl'] ?? ($g['image_url'] ?? ''),
                    $g['fileSizeKb'] ?? ($g['file_size_kb'] ?? 250),
                    $g['caption'] ?? '',
                    $g['author'] ?? 'Pengguna',
                    $g['authorEmail'] ?? ($g['author_email'] ?? ''),
                    $g['date'] ?? date('d M Y')
                ]);
            }
        }

        // Restore Videos
        if (!empty($data['videos']) && is_array($data['videos'])) {
            if ($overwrite) {
                $db->exec("DELETE FROM videos");
            }

            $insVid = $db->prepare("INSERT INTO videos (id, title, youtube_url, youtube_id, description, category, sub_category, author, author_email, date, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), youtube_url=VALUES(youtube_url), youtube_id=VALUES(youtube_id), description=VALUES(description)");

            foreach ($data['videos'] as $v) {
                $vId = $v['id'] ?? ('vid-' . time() . '-' . rand(100, 999));
                $insVid->execute([
                    $vId,
                    $v['title'] ?? 'Video Berita',
                    $v['youtubeUrl'] ?? ($v['youtube_url'] ?? ''),
                    $v['youtubeId'] ?? ($v['youtube_id'] ?? ''),
                    $v['description'] ?? '',
                    $v['category'] ?? 'EKONOMI',
                    $v['subCategory'] ?? ($v['sub_category'] ?? 'Ekonomi'),
                    $v['author'] ?? 'Tim Studio GNN',
                    $v['authorEmail'] ?? ($v['author_email'] ?? 'admin@gnn.id'),
                    $v['date'] ?? date('d M Y'),
                    $v['duration'] ?? '03:45'
                ]);
            }
        }

        echo json_encode(["success" => true, "message" => "Restore data berhasil diperbarui ke database MySQL."]);
        exit();
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal memproses backup/restore: " . $e->getMessage()
    ]);
    exit();
}
