<?php
/**
 * Good News Nusantara - Cover Image Endpoint
 * Serves cover images for articles (including base64 decoded binaries)
 * Optimized for Social Media Crawlers (WhatsApp, Facebook, Twitter, Threads, Instagram)
 */

require_once __DIR__ . '/config.php';

$articleId = trim($_GET['id'] ?? $_GET['article'] ?? '');
$fallbackPath = dirname(__DIR__) . '/icon/og-image.png';

function outputFallback($fallbackPath) {
    if (file_exists($fallbackPath)) {
        header("Content-Type: image/png");
        header("Cache-Control: public, max-age=86400");
        readfile($fallbackPath);
    } else {
        header("Content-Type: image/png");
        header("HTTP/1.0 404 Not Found");
    }
    exit();
}

if (empty($articleId)) {
    outputFallback($fallbackPath);
}

// Check if cached image file already exists on disk
$cacheDir = dirname(__DIR__) . '/uploads/covers';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$safeFileId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $articleId);
$cachedJpg = $cacheDir . '/' . $safeFileId . '.jpg';
$cachedPng = $cacheDir . '/' . $safeFileId . '.png';
$cachedWebp = $cacheDir . '/' . $safeFileId . '.webp';

if (file_exists($cachedJpg)) {
    header("Content-Type: image/jpeg");
    header("Cache-Control: public, max-age=604800");
    header("Content-Length: " . filesize($cachedJpg));
    readfile($cachedJpg);
    exit();
} elseif (file_exists($cachedPng)) {
    header("Content-Type: image/png");
    header("Cache-Control: public, max-age=604800");
    header("Content-Length: " . filesize($cachedPng));
    readfile($cachedPng);
    exit();
} elseif (file_exists($cachedWebp)) {
    header("Content-Type: image/webp");
    header("Cache-Control: public, max-age=604800");
    header("Content-Length: " . filesize($cachedWebp));
    readfile($cachedWebp);
    exit();
}

// Query article from database
try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, title, image, category FROM articles WHERE id = ?");
    $stmt->execute([$articleId]);
    $art = $stmt->fetch();

    if (!$art) {
        // Try slug search
        $targetSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $articleId));
        $all = $db->query("SELECT id, title, image, category FROM articles")->fetchAll();
        foreach ($all as $item) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $item['title']));
            if ($slug === $targetSlug || str_starts_with($slug, $targetSlug)) {
                $art = $item;
                break;
            }
        }
    }

    if (!$art || empty($art['image'])) {
        outputFallback($fallbackPath);
    }

    $img = trim($art['image']);

    // Case 1: Data URL (Base64)
    if (preg_match('#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#is', $img, $m)) {
        $ext = strtolower($m[1]);
        if ($ext === 'jpg') $ext = 'jpeg';
        $binary = base64_decode($m[2]);

        if (!empty($binary)) {
            // Save to disk cache for ultra fast subsequent requests
            $targetExt = ($ext === 'jpeg') ? 'jpg' : $ext;
            @file_put_contents($cacheDir . '/' . $safeFileId . '.' . $targetExt, $binary);

            header("Content-Type: image/" . $ext);
            header("Cache-Control: public, max-age=604800");
            header("Content-Length: " . strlen($binary));
            echo $binary;
            exit();
        }
    }

    // Case 2: External HTTP/HTTPS URL
    if (preg_match('#^https?://#i', $img)) {
        header("Location: " . $img, true, 302);
        header("Cache-Control: public, max-age=86400");
        exit();
    }

    // Case 3: Local Relative Path
    if (str_starts_with($img, '/')) {
        $localPath = dirname(__DIR__) . $img;
    } else {
        $localPath = dirname(__DIR__) . '/' . $img;
    }

    if (file_exists($localPath)) {
        $mime = mime_content_type($localPath) ?: 'image/jpeg';
        header("Content-Type: " . $mime);
        header("Cache-Control: public, max-age=604800");
        header("Content-Length: " . filesize($localPath));
        readfile($localPath);
        exit();
    }

    outputFallback($fallbackPath);

} catch (Exception $e) {
    outputFallback($fallbackPath);
}
