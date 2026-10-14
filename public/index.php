<?php
/**
 * Good News Nusantara (GNN) - Full PHP Native
 * Slogan: Jadi Tahu Indonesia
 * 100% PHP Native & MySQL - Shared Hosting Ready
 */

require_once __DIR__ . '/api/config.php';

header("Content-Type: text/html; charset=UTF-8");

$db = getDBConnection();

function formatTanggalIndonesia($timestamp = null) {
    $ts = $timestamp ? (is_numeric($timestamp) ? $timestamp : strtotime($timestamp)) : time();
    $hari = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    $namaHari = $hari[date('l', $ts)] ?? date('l', $ts);
    $tgl = date('d', $ts);
    $namaBulan = $bulan[intval(date('m', $ts))] ?? date('F', $ts);
    $thn = date('Y', $ts);

    return "$namaHari, $tgl $namaBulan $thn";
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text ?? '');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'artikel' : $text;
}

function getSiteBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'goodnewsnusantara.my.id';
    return $protocol . '://' . $host;
}

function getSafeArticleImage($img, $category = '', $articleId = '', $forSocialMeta = false) {
    $img = trim($img ?? '');
    
    // If it's a Base64 Data URL:
    if (str_starts_with($img, 'data:image/')) {
        // If it's specifically for Social Meta (WhatsApp / Facebook / Twitter cards):
        if ($forSocialMeta) {
            $base = getSiteBaseUrl();
            if (!empty($articleId)) {
                return $base . '/api/cover.php?id=' . urlencode($articleId);
            }
            return $base . '/icon/og-image.png';
        }
        // For HTML browser rendering (<img src="...">), return the actual data:image string!
        return $img;
    }

    if (!empty($img) && strlen($img) > 5) {
        if (preg_match('#^https?://#i', $img)) {
            return $img;
        }
        $base = getSiteBaseUrl();
        if (str_starts_with($img, '/')) {
            return $base . $img;
        }
        return $base . '/' . $img;
    }

    $defaults = [
        'EKONOMI' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80',
        'SAINTEK' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1200&q=80',
        'LINGKUNGAN' => 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=1200&q=80',
        'POLITIK' => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?auto=format&fit=crop&w=1200&q=80',
        'HUKUM' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=1200&q=80',
        'SOSIAL' => 'https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?auto=format&fit=crop&w=1200&q=80',
        'SEJARAH' => 'https://images.unsplash.com/photo-1596402184320-417e7178b2cd?auto=format&fit=crop&w=1200&q=80',
        'LIFESTYLE' => 'https://images.unsplash.com/photo-1511556532299-8f662fc26c06?auto=format&fit=crop&w=1200&q=80',
    ];
    $catUpper = strtoupper(trim($category));
    return $defaults[$catUpper] ?? 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';
}

function cleanAndFormatArticleContent($content) {
    if (empty($content)) return '';

    // If HTML entities were stored (e.g. &lt;div&gt; &lt;br&gt; &lt;/div&gt;)
    if (str_contains($content, '&lt;div') || str_contains($content, '&lt;p') || str_contains($content, '&lt;br') || str_contains($content, '&lt;span') || str_contains($content, '&amp;nbsp;')) {
        $content = htmlspecialchars_decode($content, ENT_QUOTES);
    }

    // Clean up empty lines or divs like <div><br></div>, <div> <br> </div>, <div>&nbsp;</div>
    $content = preg_replace('/<div>\s*(<br\s*\/?>|&nbsp;)?\s*<\/div>/i', "\n\n", $content);

    // If it has HTML tags
    if (preg_match('/<[a-z][\s\S]*>/i', $content)) {
        // Strip dangerous scripts
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        // Replace consecutive <br>
        $content = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $content);
        return $content;
    }

    // Plain text: split into paragraphs
    $paragraphs = array_filter(array_map('trim', explode("\n", $content)));
    $html = '';
    foreach ($paragraphs as $p) {
        if ($p) {
            $html .= '<p>' . htmlspecialchars($p) . '</p>';
        }
    }
    return $html ?: nl2br(htmlspecialchars($content));
}

function getArticleUrl($art) {
    if (empty($art)) return '/';
    $title = $art['title'] ?? $art['id'] ?? '';
    return '/' . slugify($title);
}

// Clean URL parser for /link-artikel/render and /link-artikel
$reqUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (preg_match('#^/([^/]+)/render/?$#i', $reqUri, $renderMatch)) {
    $_GET['view'] = 'render';
    $_GET['article'] = $renderMatch[1];
} elseif (preg_match('#^/([^/]+)/([^/]+)/render/?$#i', $reqUri, $renderMatch)) {
    $_GET['view'] = 'render';
    $_GET['article'] = $renderMatch[2];
} elseif (preg_match('#^/([a-zA-Z0-9_-]+)/?$#i', $reqUri, $artMatch)) {
    $potentialSlug = $artMatch[1];
    $ignoredPaths = ['studio', 'api', 'icon', 'sitemap', 'robots', 'index.php'];
    if (!in_array(strtolower($potentialSlug), $ignoredPaths) && !isset($_GET['article']) && !isset($_GET['view']) && !isset($_GET['page'])) {
        $_GET['article'] = $potentialSlug;
    }
}

// Handle Comments POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment') {
    $articleId = trim($_POST['article_id'] ?? '');
    $user = trim($_POST['user'] ?? '');
    $text = trim($_POST['text'] ?? '');
    if ($articleId && $user && $text) {
        $commentId = 'c-' . time() . '-' . rand(100, 999);
        $stmtCmt = $db->prepare("INSERT INTO comments (id, article_id, user, text, time) VALUES (?, ?, ?, ?, ?)");
        $stmtCmt->execute([$commentId, $articleId, $user, $text, 'Baru saja']);
        $chkArt = $db->prepare("SELECT title FROM articles WHERE id = ?");
        $chkArt->execute([$articleId]);
        $artRow = $chkArt->fetch();
        $targetUrl = $artRow ? '/' . slugify($artRow['title']) : '?article=' . urlencode($articleId);
        header("Location: " . $targetUrl . "#komentar");
        exit();
    }
}

// Handle Like POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'like') {
    $articleId = trim($_POST['article_id'] ?? '');
    if ($articleId) {
        $db->prepare("UPDATE articles SET likes = likes + 1 WHERE id = ?")->execute([$articleId]);
        $chkArt = $db->prepare("SELECT title FROM articles WHERE id = ?");
        $chkArt->execute([$articleId]);
        $artRow = $chkArt->fetch();
        $targetUrl = $artRow ? '/' . slugify($artRow['title']) : '?article=' . urlencode($articleId);
        header("Location: " . $targetUrl);
        exit();
    }
}

// Route parameters
$articleParam = $_GET['article'] ?? $_GET['id'] ?? null;
$categoryParam = $_GET['category'] ?? null;
$viewParam = $_GET['view'] ?? null;
$pageParam = $_GET['page'] ?? null;
$queryParam = trim($_GET['q'] ?? '');

// Active single article
$article = null;
if ($articleParam && $viewParam !== 'render') {
    $stmt = $db->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$articleParam]);
    $article = $stmt->fetch();
    if (!$article) {
        $targetSlug = slugify($articleParam);
        $allCheck = $db->query("SELECT * FROM articles WHERE status = 'publish' OR status IS NULL OR status = ''")->fetchAll();
        foreach ($allCheck as $chk) {
            $itemSlug = slugify($chk['title']);
            if ($itemSlug === $targetSlug || slugify($chk['id']) === $targetSlug) {
                $article = $chk;
                $articleParam = $chk['id'];
                break;
            }
        }
        if (!$article) {
            foreach ($allCheck as $chk) {
                $itemSlug = slugify($chk['title']);
                if (str_starts_with($itemSlug, $targetSlug) || str_starts_with($targetSlug, $itemSlug)) {
                    $article = $chk;
                    $articleParam = $chk['id'];
                    break;
                }
            }
        }
    }
    if ($article) {
        // Increment views
        try {
            $db->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$article['id']]);
            $article['views'] = intval($article['views']) + 1;
        } catch (Exception $e) {}

        // Bullets
        $bullets = json_decode($article['bullets'] ?: '[]', true);
        $article['bullets'] = is_array($bullets) ? $bullets : [];

        // Comments
        $cmtStmt = $db->prepare("SELECT * FROM comments WHERE article_id = ? ORDER BY created_at DESC");
        $cmtStmt->execute([$article['id']]);
        $article['comments'] = $cmtStmt->fetchAll();
    }
}

// Fetch all articles
$allArticles = [];
try {
    $stmtAll = $db->query("SELECT * FROM articles WHERE status = 'publish' OR status IS NULL OR status = '' ORDER BY created_at DESC");
    $allArticles = $stmtAll->fetchAll();
    foreach ($allArticles as &$a) {
        $b = json_decode($a['bullets'] ?: '[]', true);
        $a['bullets'] = is_array($b) ? $b : [];
    }
} catch (Exception $e) {
    $allArticles = [];
}

// Filter articles for category or search
$filteredArticles = $allArticles;
if ($categoryParam) {
    $filteredArticles = array_filter($allArticles, function($a) use ($categoryParam) {
        $c = $a['category'] ?? '';
        $sc = $a['sub_category'] ?? '';
        $match = (strcasecmp($c, $categoryParam) === 0 || strcasecmp($sc, $categoryParam) === 0);
        if (!$match && strcasecmp($categoryParam, 'SOSIAL') === 0) {
            $match = (strcasecmp($c, 'SEJARAH') === 0 || stripos($sc, 'sejarah') !== false);
        }
        if (!$match && strcasecmp($categoryParam, 'SEJARAH') === 0) {
            $match = (strcasecmp($c, 'SEJARAH') === 0 || stripos($sc, 'sejarah') !== false);
        }
        return $match;
    });
} elseif ($queryParam) {
    $filteredArticles = array_filter($allArticles, function($a) use ($queryParam) {
        $q = strtolower($queryParam);
        return str_contains(strtolower($a['title'] ?? ''), $q) || str_contains(strtolower($a['content'] ?? ''), $q) || str_contains(strtolower($a['category'] ?? ''), $q) || str_contains(strtolower($a['sub_category'] ?? ''), $q);
    });
}

// Videos from MySQL
$videos = [];
try {
    $stmtVid = $db->query("SELECT * FROM videos ORDER BY created_at DESC");
    $videos = $stmtVid->fetchAll();
} catch (Exception $e) {
    $videos = [];
}

// Gallery from MySQL
$gallery = [];
try {
    $stmtGal = $db->query("SELECT * FROM gallery ORDER BY created_at DESC");
    $gallery = $stmtGal->fetchAll();
} catch (Exception $e) {
    $gallery = [];
}

// Trending Sidebar (Rank 1-5 or most views)
$trendingList = $allArticles;
usort($trendingList, function($a, $b) {
    return ($b['popular_rank'] ?? 0) <=> ($a['popular_rank'] ?? 0) ?: ($b['views'] ?? 0) <=> ($a['views'] ?? 0);
});
$trendingArticles = array_slice($trendingList, 0, 5);

// Featured Article for Homepage (Foto 1 Headline)
$featuredArticle = null;
foreach ($allArticles as $item) {
    if (!empty($item['featured'])) {
        $featuredArticle = $item;
        break;
    }
}
if (!$featuredArticle && !empty($allArticles)) {
    $featuredArticle = $allArticles[0];
}

// Articles except featured
$otherArticles = array_values(array_filter($allArticles, function($a) use ($featuredArticle) {
    return $a['id'] !== ($featuredArticle['id'] ?? '');
}));

// Foto 1: Top News List (Right Column - 5 berita agar kolom kanan terisi penuh sejajar headline kiri)
$topNewsList = array_slice($otherArticles, 0, 5);
if (count($topNewsList) < 5) {
    $topNewsList = array_merge($topNewsList, array_slice($allArticles, 0, 5 - count($topNewsList)));
}

// Foto 3: Kabar Berita Terkini List
$kabarTerkiniList = array_slice($otherArticles, 5, 6);
if (count($kabarTerkiniList) < 4) {
    $kabarTerkiniList = array_slice($allArticles, 1, 6);
}

// Foto 4: Olahraga (Menampilkan data dari sub kategori Olahraga)
$kilasSportList = array_values(array_filter($allArticles, function($a) {
    $c = strtoupper($a['category'] ?? '');
    $sc = strtolower($a['sub_category'] ?? '');
    $t = strtolower($a['title'] ?? '');
    return str_contains($sc, 'olahraga') || str_contains($t, 'olahraga') || str_contains($t, 'timnas') || str_contains($t, 'sirkuit') || str_contains($t, 'juara') || str_contains($t, 'marquez') || str_contains($t, 'atlet') || str_contains($t, 'emas');
}));
if (empty($kilasSportList)) {
    $kilasSportList = array_values(array_filter($allArticles, function($a) {
        $c = strtoupper($a['category'] ?? '');
        return $c === 'LIFESTYLE';
    }));
}

// Widget 🔴 SEJARAH (Hanya Menampilkan Berita Sejarah Murni - Foto 1)
$sejarahArticles = array_values(array_filter($allArticles, function($a) {
    $c = strtoupper(trim($a['category'] ?? ''));
    $sc = strtolower(trim($a['sub_category'] ?? ''));
    $t = strtolower(trim($a['title'] ?? ''));
    
    $isSejarah = ($c === 'SEJARAH' || str_contains($sc, 'sejarah'));
    $hasSejarahKeyword = (
        str_contains($t, 'sejarah') || 
        str_contains($t, 'majapahit') || 
        str_contains($t, 'sriwijaya') || 
        str_contains($t, 'tarumanagara') || 
        str_contains($t, 'mataram') || 
        str_contains($t, 'kerajaan') || 
        str_contains($t, 'prasasti') || 
        str_contains($t, 'candi ') || 
        str_contains($t, 'purbakala') || 
        str_contains($t, 'arkeologi')
    );
    
    return $isSejarah || $hasSejarahKeyword;
}));

// Trending 3 Berita per Kategori: Saintek, Sosial, Lifestyle (Foto 3 Style)
$trendingSaintek = array_values(array_filter($allArticles, function($a) {
    $c = strtoupper($a['category'] ?? '');
    $sc = strtolower($a['sub_category'] ?? '');
    return $c === 'SAINTEK' || in_array($sc, ['pendidikan', 'teknologi', 'sains', 'antariksa', 'lingkungan']);
}));
usort($trendingSaintek, function($a, $b) {
    return ($b['popular_rank'] ?? 0) <=> ($a['popular_rank'] ?? 0) ?: ($b['views'] ?? 0) <=> ($a['views'] ?? 0) ?: strtotime($b['created_at'] ?? 0) <=> strtotime($a['created_at'] ?? 0);
});
$trendingSaintek = array_slice($trendingSaintek, 0, 3);

$trendingSosial = array_values(array_filter($allArticles, function($a) {
    $c = strtoupper($a['category'] ?? '');
    $sc = strtolower($a['sub_category'] ?? '');
    return $c === 'SOSIAL' || $c === 'SEJARAH' || in_array($sc, ['sosial', 'sejarah', 'budaya', 'gastronomi', 'seni & sastra']);
}));
usort($trendingSosial, function($a, $b) {
    return ($b['popular_rank'] ?? 0) <=> ($a['popular_rank'] ?? 0) ?: ($b['views'] ?? 0) <=> ($a['views'] ?? 0) ?: strtotime($b['created_at'] ?? 0) <=> strtotime($a['created_at'] ?? 0);
});
$trendingSosial = array_slice($trendingSosial, 0, 3);

$trendingLifestyle = array_values(array_filter($allArticles, function($a) {
    $c = strtoupper($a['category'] ?? '');
    $sc = strtolower($a['sub_category'] ?? '');
    return $c === 'LIFESTYLE' || in_array($sc, ['religi', 'olahraga', 'hiburan', 'kesehatan', 'media kreatif', 'film & series']);
}));
usort($trendingLifestyle, function($a, $b) {
    return ($b['popular_rank'] ?? 0) <=> ($a['popular_rank'] ?? 0) ?: ($b['views'] ?? 0) <=> ($a['views'] ?? 0) ?: strtotime($b['created_at'] ?? 0) <=> strtotime($a['created_at'] ?? 0);
});
$trendingLifestyle = array_slice($trendingLifestyle, 0, 3);

// SEO & Social Media Details
$baseUrl = getSiteBaseUrl();
$pageTitle = "Good News Nusantara | Jadi Tahu Indonesia";
$pageDesc = "Good News Nusantara - Berita Positif Nusantara & Jadi Tahu Indonesia. Media informasi kabar baik terpercaya, inovasi lokal anak bangsa, prestasi, kisah inspiratif, budaya, pariwisata, dan potensi kekayaan Indonesia demi membangun optimisme bangsa.";
$pageImage = $baseUrl . "/icon/og-image.png";
$pageUrl = $baseUrl . "/";
$articlePublishedTime = "";
$articleModifiedTime = "";
$articleAuthor = "Redaksi Good News Nusantara";
$articleCategory = "KABAR BAIK";
$rawHeadline = "Good News Nusantara | Jadi Tahu Indonesia";

if ($article) {
    $rawHeadline = trim(strip_tags($article['title'] ?? ''));
    $pageTitle = $rawHeadline . " - Good News Nusantara | Jadi Tahu Indonesia";
    
    // Clean excerpt for social shares (WhatsApp, Facebook, Twitter, Threads)
    $rawContent = strip_tags(html_entity_decode($article['content'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $cleanDesc = trim(preg_replace('/\s+/', ' ', $rawContent));
    if (empty($cleanDesc)) {
        $cleanDesc = "Baca ulasan lengkap kabar baik dan inspiratif nusantara ini hanya di Good News Nusantara - Jadi Tahu Indonesia.";
    }
    $pageDesc = mb_substr($cleanDesc, 0, 160) . (mb_strlen($cleanDesc) > 160 ? '...' : '');
    
    // Safe Absolute Image URL (Social Meta Crawler mode: true)
    $pageImage = getSafeArticleImage($article['image'] ?? '', $article['category'] ?? '', $article['id'] ?? '', true);
    
    // Canonical Article URL using dynamic baseUrl
    $pageUrl = $baseUrl . "/" . slugify($article['title']);
    
    // Article Metadata
    $articlePublishedTime = !empty($article['created_at']) ? date('c', strtotime($article['created_at'])) : date('c');
    $articleModifiedTime = !empty($article['updated_at']) ? date('c', strtotime($article['updated_at'])) : $articlePublishedTime;
    $articleAuthor = !empty($article['author']) ? trim($article['author']) : 'Redaksi Good News Nusantara';
    $articleCategory = !empty($article['category']) ? trim($article['category']) : 'KABAR BAIK';
} elseif ($categoryParam) {
    $pageTitle = "Kategori " . htmlspecialchars($categoryParam, ENT_QUOTES, 'UTF-8') . " - Good News Nusantara | Jadi Tahu Indonesia";
} elseif ($queryParam) {
    $pageTitle = "Pencarian: " . htmlspecialchars($queryParam, ENT_QUOTES, 'UTF-8') . " - Good News Nusantara | Jadi Tahu Indonesia";
} elseif ($viewParam === 'tv') {
    $pageTitle = "GNN TV & Video Nusantara - Good News Nusantara | Jadi Tahu Indonesia";
} elseif ($viewParam === 'gallery') {
    $pageTitle = "Galeri Foto Nusantara - Good News Nusantara | Jadi Tahu Indonesia";
} elseif ($pageParam === 'pedoman') {
    $pageTitle = "Pedoman Pemberitaan Media Siber - Good News Nusantara | Jadi Tahu Indonesia";
} elseif ($pageParam === 'aturan') {
    $pageTitle = "Aturan & Ketentuan Penggunaan - Good News Nusantara | Jadi Tahu Indonesia";
} elseif ($pageParam === 'privasi') {
    $pageTitle = "Kebijakan Privasi - Good News Nusantara | Jadi Tahu Indonesia";
} elseif ($pageParam === 'redaksi') {
    $pageTitle = "Susunan Dewan Redaksi - Good News Nusantara | Jadi Tahu Indonesia";
} elseif ($pageParam === 'tentang') {
    $pageTitle = "Tentang Good News Nusantara | Jadi Tahu Indonesia";
} elseif ($pageParam === 'kontak') {
    $pageTitle = "Kontak Iklan & Kerjasama - Good News Nusantara | Jadi Tahu Indonesia";
}

// Detect image mime type for og:image:type
$imageMime = 'image/jpeg';
if (preg_match('#\.(png)(\?.*)?$#i', $pageImage)) {
    $imageMime = 'image/png';
} elseif (preg_match('#\.(webp)(\?.*)?$#i', $pageImage)) {
    $imageMime = 'image/webp';
}

$categoriesList = ['KABAR BAIK', 'INSPIRASI', 'PARIWISATA', 'BUDAYA', 'TEKNOLOGI', 'EKONOMI', 'PENDIDIKAN', 'LINGKUNGAN'];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Google Verification & Indexing -->
  <meta name="google-site-verification" content="google347822a2c05e18bd" />
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />
  <meta name="author" content="Redaksi Good News Nusantara" />
  <link rel="canonical" href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" />

  <!-- 20 Kata Kunci Strategis SEO GNN -->
  <meta name="description" content="<?= htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="keywords" content="Good News Nusantara, GNN Indonesia, Berita Positif Nusantara, Kabar Baik Indonesia, Kabar baik daerah Indonesia, Inovasi lokal anak bangsa, Prestasi Indonesia terbaru, Kisah inspiratif Nusantara, Pembangunan daerah positif, Kabar baik dari pelosok negeri, Jadi Tahu Indonesia, Optimisme bangsa Indonesia, Media informasi positif Indonesia, Kabar inspiratif pemuda, Potensi dan kekayaan Indonesia, Budaya dan pariwisata Nusantara, Karya anak bangsa inspiratif, Teknologi dan sains lokal Indonesia, UMKM dan ekonomi kreatif Indonesia, Seni dan tradisi Nusantara" />

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/icon/favicon.png" />
  <link rel="apple-touch-icon" href="/icon/favicon.png" />
  <meta name="theme-color" content="#CC0000" />

  <!-- Open Graph / WhatsApp / Facebook / Instagram / Threads -->
  <meta property="og:site_name" content="Good News Nusantara" />
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:type" content="<?= $article ? 'article' : 'website' ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:image:secure_url" content="<?= htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:image:type" content="<?= $imageMime ?>" />
  <meta property="og:image:alt" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="og:locale" content="id_ID" />
  <?php if ($article): ?>
  <meta property="article:published_time" content="<?= $articlePublishedTime ?>" />
  <meta property="article:modified_time" content="<?= $articleModifiedTime ?>" />
  <meta property="article:author" content="<?= htmlspecialchars($articleAuthor, ENT_QUOTES, 'UTF-8') ?>" />
  <meta property="article:section" content="<?= htmlspecialchars($articleCategory, ENT_QUOTES, 'UTF-8') ?>" />
  <?php endif; ?>

  <!-- Twitter / X Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:site" content="@gnn_id" />
  <meta name="twitter:creator" content="@gnn_id" />
  <meta name="twitter:url" content="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="twitter:description" content="<?= htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="twitter:image" content="<?= htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8') ?>" />
  <meta name="twitter:image:alt" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>" />

  <!-- Direct Image Fallbacks for WhatsApp Scraper -->
  <link rel="image_src" href="<?= htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8') ?>" />
  <meta itemprop="name" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>" />
  <meta itemprop="description" content="<?= htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8') ?>" />
  <meta itemprop="image" content="<?= htmlspecialchars($pageImage, ENT_QUOTES, 'UTF-8') ?>" />

  <!-- Schema.org JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebSite",
        "@id": "<?= $baseUrl ?>/#website",
        "url": "<?= $baseUrl ?>/",
        "name": "Good News Nusantara | Jadi Tahu Indonesia",
        "alternateName": ["GNN", "Good News Nusantara", "GNN Indonesia"],
        "description": "Good News Nusantara - Berita Positif Nusantara & Jadi Tahu Indonesia.",
        "inLanguage": "id-ID"
      },
      {
        "@type": "NewsMediaOrganization",
        "@id": "<?= $baseUrl ?>/#organization",
        "name": "Good News Nusantara",
        "url": "<?= $baseUrl ?>/",
        "logo": "<?= $baseUrl ?>/icon/logow.png",
        "slogan": "Jadi Tahu Indonesia"
      }
      <?php if ($article): ?>,
      {
        "@type": "NewsArticle",
        "headline": <?= json_encode($rawHeadline, JSON_UNESCAPED_UNICODE) ?>,
        "image": [<?= json_encode($pageImage, JSON_UNESCAPED_SLASHES) ?>],
        "datePublished": "<?= $articlePublishedTime ?>",
        "dateModified": "<?= $articleModifiedTime ?>",
        "author": {
          "@type": "Person",
          "name": <?= json_encode($articleAuthor, JSON_UNESCAPED_UNICODE) ?>
        },
        "publisher": {
          "@type": "Organization",
          "name": "Good News Nusantara",
          "logo": {
            "@type": "ImageObject",
            "url": "https://gnn.web.id/icon/logow.png"
          }
        },
        "description": <?= json_encode($pageDesc, JSON_UNESCAPED_UNICODE) ?>,
        "mainEntityOfPage": {
          "@type": "WebPage",
          "@id": <?= json_encode($pageUrl, JSON_UNESCAPED_SLASHES) ?>
        }
      }
      <?php endif; ?>
    ]
  }
  </script>

  <!-- Google Fonts & Tailwind CSS CDN (Zero Build Tools) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Roboto+Serif:ital,opsz,wght@0,8..144,400..900;1,8..144,400..900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          aspectRatio: {
            '16/9': '16 / 9',
            'video': '16 / 9',
            '9/16': '9 / 16',
            '4/5': '4 / 5',
            '4/3': '4 / 3',
          },
          colors: {
            gnn: {
              red: '#CC0000',
              dark: '#990000',
              navy: '#0f172a'
            }
          },
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
            serif: ['"Roboto Serif"', 'Georgia', 'serif']
          }
        }
      }
    }
  </script>
  <style>
    @font-face {
      font-family: 'Roboto Serif';
      src: url('/font/Roboto_Serif/RobotoSerif-VariableFont_GRAD,opsz,wdth,wght.ttf') format('truetype-variations'),
           url('/font/Roboto_Serif/static/RobotoSerif-Regular.ttf') format('truetype');
      font-weight: 100 900;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'Roboto Serif';
      src: url('/font/Roboto_Serif/static/RobotoSerif-Bold.ttf') format('truetype');
      font-weight: 700;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'Roboto Serif';
      src: url('/font/Roboto_Serif/static/RobotoSerif-ExtraBold.ttf') format('truetype');
      font-weight: 800;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'Roboto Serif';
      src: url('/font/Roboto_Serif/static/RobotoSerif-Black.ttf') format('truetype');
      font-weight: 900;
      font-style: normal;
      font-display: swap;
    }
    @font-face {
      font-family: 'Roboto Serif';
      src: url('/font/Roboto_Serif/RobotoSerif-Italic-VariableFont_GRAD,opsz,wdth,wght.ttf') format('truetype-variations'),
           url('/font/Roboto_Serif/static/RobotoSerif-Italic.ttf') format('truetype');
      font-weight: 100 900;
      font-style: italic;
      font-display: swap;
    }
    .font-serif, h1.font-serif, h2.font-serif, h3.font-serif, .article-title, .story-title, #canvasTextFull, #canvasTextSplit {
      font-family: 'Roboto Serif', Georgia, serif !important;
    }
    .prose p {
      margin-bottom: 1.25rem;
      line-height: 1.85;
    }
    .prose div {
      margin-bottom: 0.75rem;
      line-height: 1.85;
    }
    .prose h2, .prose h3 {
      font-family: 'Roboto Serif', Georgia, serif;
      font-weight: 800;
      margin-top: 1.75rem;
      margin-bottom: 0.75rem;
    }
    .prose blockquote {
      border-left: 4px solid #CC0000;
      padding-left: 1rem;
      font-style: italic;
      margin: 1.5rem 0;
    }
    .prose figure {
      margin: 1.5rem 0;
    }
    .prose figure img {
      border-radius: 1rem;
      max-width: 100%;
      height: auto;
    }
    @keyframes marquee {
      0% { transform: translateX(0%); }
      100% { transform: translateX(-50%); }
    }
    .animate-marquee {
      display: inline-flex;
      animation: marquee 35s linear infinite;
    }
    .animate-marquee:hover {
      animation-play-state: paused;
    }
    html, body {
      max-width: 100%;
      overflow-x: hidden;
    }
    .card-cover {
      aspect-ratio: 16 / 9 !important;
      width: 100% !important;
      position: relative !important;
      overflow: hidden !important;
      background-color: #f4f4f5;
    }
    .card-cover img {
      width: 100% !important;
      height: 100% !important;
      object-fit: cover !important;
      object-position: center !important;
      display: block !important;
    }
  </style>
  <script>
    if (localStorage.getItem('gnn_theme') === 'dark') {
      document.documentElement.classList.add('dark');
    }
    function toggleDarkMode() {
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.setItem('gnn_theme', isDark ? 'dark' : 'light');
    }
    function copyArticleLink() {
      navigator.clipboard.writeText(window.location.href).then(function() {
        var btn = document.getElementById('btnCopyLink');
        if (btn) {
          var orig = btn.innerHTML;
          btn.innerHTML = '<svg class="w-4 h-4 text-emerald-600 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
          setTimeout(function() { btn.innerHTML = orig; }, 2000);
        }
      });
    }
  </script>
</head>
<body class="bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 font-sans antialiased flex flex-col min-h-screen max-w-full overflow-x-hidden transition-colors duration-200">

  <!-- ============================================================== -->
  <!-- GNFI OFFICIAL HEADER (RESPONSIVE & OVERFLOW-SAFE) -->
  <!-- ============================================================== -->
  <header class="bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 sticky top-0 z-40 shadow-xs transition-colors w-full">
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6 flex items-center justify-between gap-2 lg:gap-3 h-16 sm:h-20 w-full">
      
      <!-- Left: Official Emblem Logo (Full 0px top & full header height) -->
      <a href="/" class="flex items-center shrink-0 h-full self-stretch py-0 my-0 group" title="Good News Nusantara">
        <img src="/icon/logoh.png" alt="Good News Nusantara" class="h-full w-auto max-h-full object-contain object-left dark:hidden py-0 my-0 block transition-transform group-hover:scale-102" onerror="this.onerror=null; this.src='/logoh.png';">
        <img src="/icon/logow.png" alt="Good News Nusantara" class="h-full w-auto max-h-full object-contain object-left hidden dark:block py-0 my-0 block transition-transform group-hover:scale-102" onerror="this.onerror=null; this.src='/logow.png';">
      </a>

      <!-- Center: Category Dropdown Navigation Menu -->
      <nav class="hidden lg:flex items-center gap-1 xl:gap-3 flex-1 justify-center px-1">
        <?php
        $navItems = [
            ['name' => 'Ekonomi', 'subs' => ['Ekonomi', 'Bisnis', 'Investasi', 'Ketenagakerjaan']],
            ['name' => 'Saintek', 'subs' => ['Pendidikan', 'Teknologi', 'Sains', 'Antariksa', 'Lingkungan']],
            ['name' => 'Politik', 'subs' => ['Kebijakan', 'Nasional', 'Asean', 'Internasional']],
            ['name' => 'Hukum', 'subs' => ['Hukum', 'Kriminal', 'Investigasi']],
            ['name' => 'Sosial', 'subs' => ['Sosial', 'Sejarah', 'Budaya', 'Gastronomi', 'Seni & Sastra']],
            ['name' => 'Lifestyle', 'subs' => ['Religi', 'Olahraga', 'Hiburan', 'Kesehatan', 'Media Kreatif', 'Film & Series']]
        ];

        foreach ($navItems as $item):
            $isActive = (strcasecmp($categoryParam ?? '', $item['name']) === 0);
            $hasSubs = !empty($item['subs']);
        ?>
          <div class="relative group py-2">
            <a href="?category=<?= urlencode(strtoupper($item['name'])) ?>"
               class="text-xs xl:text-[13.5px] font-bold transition-all flex items-center gap-0.5 xl:gap-1 whitespace-nowrap px-1.5 xl:px-2 py-1 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 <?= $isActive ? 'text-[#CC0000]' : 'text-zinc-900 dark:text-zinc-100 hover:text-[#CC0000]' ?>">
              <span><?= htmlspecialchars($item['name']) ?></span>
              <?php if ($hasSubs): ?>
                <svg class="w-3 h-3 stroke-[2.5] text-zinc-500 transition-transform duration-200 group-hover:rotate-180 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                </svg>
              <?php endif; ?>
              <?php if ($isActive): ?>
                <span class="absolute bottom-0 left-1 right-1 h-[2.5px] bg-[#CC0000] rounded-full"></span>
              <?php endif; ?>
            </a>

            <?php if ($hasSubs): ?>
              <div class="absolute top-full left-0 mt-0 w-48 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xl py-2 z-50 hidden group-hover:block transition-all duration-150">
                <?php foreach ($item['subs'] as $sub): ?>
                  <a href="?q=<?= urlencode($sub) ?>"
                     class="w-full text-left px-4 py-2 text-xs sm:text-sm font-semibold transition-colors flex items-center justify-between text-zinc-700 dark:text-zinc-300 hover:text-[#CC0000] hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    <span><?= htmlspecialchars($sub) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </nav>

      <!-- Right: Search Pill, Dark Mode Moon, Tulis Button -->
      <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
        <!-- Search Box Pill -->
        <form method="GET" action="/" class="relative hidden sm:flex items-center w-24 md:w-32 xl:w-44 transition-all">
          <input type="text" name="q" value="<?= htmlspecialchars($queryParam) ?>" placeholder="Cari..." class="w-full bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-full py-1.5 pl-3 pr-7 text-xs font-medium text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:border-[#CC0000] focus:bg-white dark:focus:bg-zinc-900 transition-all">
          <button type="submit" class="absolute right-2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
          </button>
        </form>

        <!-- Dark Mode Toggle Button (Moon Icon) -->
        <button type="button" onclick="toggleDarkMode()" class="p-1.5 sm:p-2 rounded-full text-zinc-700 dark:text-amber-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition cursor-pointer shrink-0" title="Ubah Mode Gelap/Terang">
          <svg class="w-5 h-5 fill-current dark:hidden" viewBox="0 0 24 24">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <svg class="w-5 h-5 fill-current hidden dark:block" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="5"></circle>
            <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"></path>
          </svg>
        </button>

        <!-- Red Tulis Button (shrink-0 ensures it never gets cut off) -->
        <a href="/studio/?tab=write" class="bg-[#CC0000] hover:bg-red-700 text-white rounded-lg px-3 sm:px-4 py-1.5 text-xs sm:text-sm font-bold transition cursor-pointer shadow-xs shrink-0 flex items-center justify-center">
          Tulis
        </a>

        <!-- Mobile Menu Toggle -->
        <button type="button" onclick="document.getElementById('mobileNavDrawer').classList.toggle('hidden')" class="p-1.5 lg:hidden text-zinc-700 dark:text-zinc-200 shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
      </div>
    </div>

    <!-- Mobile Drawer -->
    <div id="mobileNavDrawer" class="hidden lg:hidden border-t border-zinc-200 dark:border-zinc-800 p-4 bg-white dark:bg-zinc-900 space-y-3">
      <form method="GET" action="/" class="relative w-full">
        <input type="text" name="q" value="<?= htmlspecialchars($queryParam) ?>" placeholder="Cari berita..." class="w-full bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-full py-2 pl-3.5 pr-8 text-xs font-medium text-zinc-900 dark:text-white">
      </form>
      <div class="grid grid-cols-2 gap-2 text-xs font-bold pt-2">
        <a href="/" class="p-2 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white">SEMUA</a>
        <?php foreach ($navItems as $item): ?>
          <a href="?category=<?= urlencode(strtoupper($item['name'])) ?>" class="p-2 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white"><?= htmlspecialchars($item['name']) ?></a>
        <?php endforeach; ?>
        <a href="?view=tv" class="p-2 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white">VIDEO TV</a>
        <a href="?view=gallery" class="p-2 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white">GALERI</a>
      </div>
    </div>
  </header>

  <!-- ============================================================== -->
  <!-- BREAKING NEWS TICKER BAR (CNN SIGNATURE RED BAR) -->
  <!-- ============================================================== -->
  <?php
  $breakingArticles = array_filter($allArticles, function($a) {
      return !empty($a['breaking']);
  });
  if (empty($breakingArticles)) {
      $breakingArticles = array_slice($allArticles, 0, 5);
  }
  ?>
  <div class="bg-[#CC0000] text-white py-1.5 px-4 text-xs font-semibold flex items-center gap-3 overflow-hidden shadow-xs">
    <div class="bg-white text-[#CC0000] px-2.5 py-0.5 font-black uppercase text-[10px] tracking-wider shrink-0 rounded-xs flex items-center gap-1 shadow-xs">
      <svg class="w-3.5 h-3.5 fill-current text-[#CC0000]" viewBox="0 0 24 24"><path d="M12 23c6.075 0 11-4.925 11-11 0-4.043-2.185-7.585-5.464-9.518-.328-.194-.746-.113-.984.19-.24.305-.188.75.122 1 .288.232.553.488.793.766-1.558-.093-3.082.463-4.225 1.54C11.516 7.643 10.74 9.948 11.08 12.19c.07.45-.23.87-.68.94-.45.07-.87-.23-.94-.68-.39-2.58.46-5.23 2.34-7.01-1.34.62-2.48 1.65-3.23 2.94-.95 1.63-1.22 3.56-.76 5.43.08.33-.08.67-.39.8-.31.13-.67.01-.84-.28C6.18 13.62 6 12.83 6 12c0-1.8.63-3.46 1.7-4.78.26-.32.22-.79-.09-1.06-.31-.27-.78-.24-1.06.07C5.04 8.01 4.25 10.12 4.25 12.4c0 5.86 4.75 10.6 10.6 10.6H12z"/></svg>
      <span>BREAKING NEWS</span>
    </div>
    
    <div class="overflow-hidden whitespace-nowrap flex-grow relative">
      <div class="inline-flex gap-8 text-[12px] font-medium animate-marquee">
        <?php foreach (array_merge($breakingArticles, $breakingArticles) as $bItem): ?>
          <a href="/<?= slugify($bItem['title']) ?>" class="hover:underline inline-flex items-center gap-2 shrink-0 text-white">
            <span class="font-bold text-yellow-300">[<?= htmlspecialchars($bItem['category']) ?>]</span>
            <span><?= htmlspecialchars($bItem['title']) ?></span>
            <span class="text-white/60">•</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- MAIN BODY -->
  <main class="flex-1 max-w-7xl mx-auto px-4 py-6 w-full">

    <?php if ($article): ?>
      <!-- ========================================== -->
      <!-- ARTICLE DETAIL VIEW -->
      <!-- ========================================== -->
      <article class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-8 bg-white p-6 sm:p-8 rounded-2xl border border-zinc-200 shadow-xs">
          <!-- Breadcrumb -->
          <div class="flex items-center gap-2 text-xs font-semibold text-zinc-500 mb-3">
            <a href="/" class="hover:text-red-700">Home</a>
            <span>/</span>
            <a href="?category=<?= urlencode($article['category']) ?>" class="text-red-700 uppercase"><?= htmlspecialchars($article['category']) ?></a>
          </div>

          <!-- Red Uppercase Category Tag (Matching Foto Screenshot) -->
          <span class="text-[#CC0000] font-black text-xs sm:text-sm tracking-wider uppercase block mb-2">
            <?= htmlspecialchars($article['sub_category'] ?: $article['category']) ?>
          </span>

          <!-- Title (Clean Sans-Serif Plus Jakarta Sans like screenshot for maximum readability) -->
          <h1 class="font-sans font-black text-2xl sm:text-4xl text-zinc-900 leading-tight mb-3">
            <?= htmlspecialchars($article['title']) ?>
          </h1>

          <!-- Meta Author & Date -->
          <div class="flex flex-wrap items-center justify-between border-y border-zinc-100 py-3 mb-6 text-xs text-zinc-600 gap-2">
            <div class="flex items-center gap-3">
              <span class="font-bold text-zinc-800"><?= htmlspecialchars($article['author'] ?: 'Redaksi GNN') ?></span>
              <span>•</span>
              <span><?= htmlspecialchars($article['date'] ?: date('d M Y', strtotime($article['created_at']))) ?></span>
              <span>•</span>
              <span><?= htmlspecialchars($article['location'] ?: 'Good News Nusantara') ?></span>
            </div>
            <div class="flex items-center gap-4">
              <span>Dibaca: <strong><?= number_format(intval($article['views'])) ?></strong></span>
              <span>Suka: <strong><?= number_format(intval($article['likes'])) ?></strong></span>
            </div>
          </div>

          <!-- Featured Image -->
          <figure class="mb-6 rounded-xl overflow-hidden border border-zinc-200">
            <img src="<?= htmlspecialchars(getSafeArticleImage($article['image'] ?? '', $article['category'] ?? '')) ?>" alt="<?= htmlspecialchars($article['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="w-full h-auto max-h-[500px] object-cover">
            <?php if (!empty($article['caption'])): ?>
              <figcaption class="p-3 text-xs text-zinc-500 bg-zinc-50 border-t border-zinc-200">
                <?= htmlspecialchars($article['caption']) ?>
              </figcaption>
            <?php endif; ?>
          </figure>

          <!-- Article Content -->
          <div class="prose max-w-none text-zinc-800 dark:text-zinc-200 text-base leading-relaxed space-y-4 mb-8">
            <?= cleanAndFormatArticleContent($article['content']) ?>
          </div>

          <!-- ACTION BAR: RENDER STORY, LIKE & SOCIAL SHARE -->
          <div class="border-y border-zinc-200 dark:border-zinc-800 py-5 my-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            
            <!-- Left: Render Desain Story & Suka -->
            <div class="flex flex-wrap items-center gap-2.5">
              <!-- Tombol Render Desain Story 9:16 -->
              <a href="/<?= slugify($article['title']) ?>/render" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs shadow-sm transition hover:shadow-md transform active:scale-95" title="Render ke Template Desain Story 9:16">
                <svg class="w-4 h-4 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Render Desain Story (9:16)</span>
              </a>

              <!-- Tombol Suka -->
              <form method="POST" action="" class="inline-block m-0">
                <input type="hidden" name="action" value="like">
                <input type="hidden" name="article_id" value="<?= htmlspecialchars($article['id']) ?>">
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-red-50 hover:bg-red-100 text-[#CC0000] font-bold text-xs transition border border-red-200 cursor-pointer shadow-2xs" title="Sukai Berita Ini">
                  <svg class="w-4 h-4 fill-current text-[#CC0000]" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                  <span>Suka</span>
                  <span class="text-zinc-500 font-semibold">(<?= number_format(intval($article['likes'])) ?>)</span>
                </button>
              </form>
            </div>

            <!-- Right: Share ke Media Sosial -->
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider mr-1">Bagikan:</span>
              
              <!-- WhatsApp -->
              <a href="https://api.whatsapp.com/send?text=<?= urlencode($article['title'] . ' ' . $pageUrl) ?>" target="_blank" class="w-9 h-9 rounded-full bg-[#25D366] hover:bg-[#1EBE5D] text-white flex items-center justify-center transition shadow-xs hover:scale-105" title="Bagikan ke WhatsApp">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
              </a>

              <!-- Facebook -->
              <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($pageUrl) ?>" target="_blank" class="w-9 h-9 rounded-full bg-[#1877F2] hover:bg-[#166FE5] text-white flex items-center justify-center transition shadow-xs hover:scale-105" title="Bagikan ke Facebook">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
              </a>

              <!-- Twitter / X -->
              <a href="https://twitter.com/intent/tweet?text=<?= urlencode($article['title']) ?>&url=<?= urlencode($pageUrl) ?>" target="_blank" class="w-9 h-9 rounded-full bg-black hover:bg-zinc-800 text-white flex items-center justify-center transition shadow-xs hover:scale-105" title="Bagikan ke X">
                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
              </a>

              <!-- Threads -->
              <a href="https://www.threads.net/intent/post?text=<?= urlencode($article['title'] . ' ' . $pageUrl) ?>" target="_blank" class="w-9 h-9 rounded-full bg-[#101010] hover:bg-black text-white flex items-center justify-center transition shadow-xs hover:scale-105 border border-zinc-700/50" title="Bagikan ke Threads">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M18.263 11.097c-.03-3.486-1.92-5.586-5.111-5.586-2.13 0-3.922.963-4.863 2.499l2.062 1.438c.535-.843 1.272-1.543 2.628-1.543 1.528 0 2.318.85 2.544 2.431a15 15 0 0 0-2.236-.173c-4.125 0-6.068 1.867-6.068 4.336s1.943 3.99 4.804 3.99c3.139 0 5.013-2.115 5.781-4.735.798.361 1.348 1.204 1.348 2.47 0 3.387-3.907 5.232-7.22 5.232-4.885 0-8.077-3.207-8.077-8.424 0-6.392 4.223-10.487 9.9-10.487 3.808 0 5.69 1.671 6.97 3.914l2.108-1.475C21.44 2.078 18.331 0 13.663 0 6.227 0 1.168 5.277 1.168 12.934c0 7 4.953 11.066 10.856 11.066 4.878 0 9.809-2.846 9.809-7.716 0-2.545-1.46-4.231-3.569-5.187m-6.33 4.855c-1.077 0-2.026-.512-2.026-1.453 0-1.483 1.822-1.934 3.606-1.934.678 0 1.34.045 1.927.173-.422 1.927-1.671 3.215-3.508 3.214Z"/></svg>
              </a>

              <!-- Instagram -->
              <a href="https://www.instagram.com/" target="_blank" onclick="copyArticleLink(); alert('Tautan artikel telah disalin ke clipboard! Anda dapat menempelkannya (paste) di Instagram Story atau Direct Message.');" class="w-9 h-9 rounded-full bg-gradient-to-tr from-[#FD1D1D] via-[#E1306C] to-[#833AB4] hover:opacity-90 text-white flex items-center justify-center transition shadow-xs hover:scale-105" title="Bagikan ke Instagram (Salin Link & Buka Instagram)">
                <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
              </a>

              <!-- Gmail (Official Google Multi-color Logo) -->
              <a href="https://mail.google.com/mail/?view=cm&fs=1&su=<?= urlencode($article['title']) ?>&body=<?= urlencode($article['title'] . "\n\n" . $pageUrl) ?>" target="_blank" class="w-9 h-9 rounded-full bg-white hover:bg-zinc-50 border border-zinc-200 shadow-2xs flex items-center justify-center transition hover:scale-105" title="Bagikan lewat Gmail">
                <svg class="w-4.5 h-4.5" viewBox="0 0 512 512">
                  <path fill="#4285F4" d="M34.9 448h81.5V250.2L0 163v250.2C0 432.5 15.7 448 34.9 448"/>
                  <path fill="#34A853" d="M395.6 448h81.5c19.3 0 34.9-15.7 34.9-34.9V163l-116.4 87.3"/>
                  <path fill="#FBBC04" d="M395.6 99v151.3L512 163v-46.5c0-43.2-49.3-67.8-83.8-41.9"/>
                  <path fill="#EA4335" d="M116.4 250.2V99L256 203.7 395.6 99v151.3L256 355"/>
                  <path fill="#C5221F" d="M0 116.4V163l116.4 87.3V99L83.8 74.5C49.2 48.6 0 73.2 0 116.4"/>
                </svg>
              </a>

              <!-- Salin Tautan (Copy Link) -->
              <button type="button" onclick="copyArticleLink()" id="btnCopyLink" class="w-9 h-9 rounded-full bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 flex items-center justify-center transition shadow-xs hover:scale-105 cursor-pointer" title="Salin Tautan Artikel">
                <svg class="w-4 h-4 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- RIGHT SIDEBAR -->
        <aside class="lg:col-span-4 space-y-6">
          <!-- TRENDING WIDGET -->
          <div class="bg-white p-5 rounded-2xl border border-zinc-200 shadow-xs">
            <h3 class="font-serif font-black text-lg text-zinc-900 border-b border-zinc-200 pb-3 mb-4 flex items-center justify-between">
              <span>TRENDING NUSANTARA</span>
              <span class="text-xs text-red-700 font-sans font-bold">TOP 5</span>
            </h3>
            <div class="space-y-4">
              <?php foreach ($trendingArticles as $idx => $trend): ?>
                <a href="/<?= slugify($trend['title']) ?>" class="flex items-start gap-3 group">
                  <span class="font-serif font-black text-2xl text-red-700 w-6 leading-none"><?= $idx + 1 ?></span>
                  <div class="flex-1">
                    <span class="text-[10px] font-bold text-red-700 uppercase"><?= htmlspecialchars($trend['category']) ?></span>
                    <h4 class="text-xs font-bold text-zinc-900 group-hover:text-red-700 line-clamp-2 transition leading-snug">
                      <?= htmlspecialchars($trend['title']) ?>
                    </h4>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </aside>
      </article>

    <?php elseif ($viewParam === 'render'): ?>
      <!-- ========================================== -->
      <!-- RENDER DESAIN TEMPLATE / STORY 9:16 CREATOR -->
      <!-- ========================================== -->
      <?php
      $selectedRenderArticle = null;
      if ($articleParam) {
          $targetSlug = slugify($articleParam);
          // 1. Exact ID
          foreach ($allArticles as $artItem) {
              if ($artItem['id'] === $articleParam) {
                  $selectedRenderArticle = $artItem;
                  break;
              }
          }
          // 2. Exact Title Slug
          if (!$selectedRenderArticle) {
              foreach ($allArticles as $artItem) {
                  if (slugify($artItem['title']) === $targetSlug) {
                      $selectedRenderArticle = $artItem;
                      break;
                  }
              }
          }
          // 3. Partial Title Slug match
          if (!$selectedRenderArticle) {
              foreach ($allArticles as $artItem) {
                  $itemSlug = slugify($artItem['title']);
                  if (strpos($itemSlug, $targetSlug) !== false || strpos($targetSlug, $itemSlug) !== false) {
                      $selectedRenderArticle = $artItem;
                      break;
                  }
              }
          }
      }
      if (!$selectedRenderArticle && !empty($allArticles)) {
          $selectedRenderArticle = $allArticles[0];
      }

      $initialTitle = !empty($selectedRenderArticle['title']) ? $selectedRenderArticle['title'] : 'Inovasi Anak Bangsa Membawa Kemajuan Bagi Nusantara';
      $initialImage = getSafeArticleImage($selectedRenderArticle['image'] ?? '', $selectedRenderArticle['category'] ?? '');
      $initialCategory = !empty($selectedRenderArticle['category']) ? $selectedRenderArticle['category'] : 'INSPIRASI';
      $initialCredit = !empty($selectedRenderArticle['image_credit']) ? $selectedRenderArticle['image_credit'] : (!empty($selectedRenderArticle['caption']) ? $selectedRenderArticle['caption'] : 'Dok. Redaksi Good News Nusantara');
      ?>
      <div class="mb-8">
        <div class="border-b border-zinc-200 dark:border-zinc-800 pb-5 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 mb-1.5 text-xs font-semibold text-zinc-500">
              <a href="/" class="hover:text-red-700 transition">Beranda</a>
              <span>/</span>
              <span class="text-[#CC0000] font-bold">Desain Grafis &amp; Story</span>
            </div>
            <h1 class="font-serif font-black text-2xl sm:text-3xl text-zinc-900 dark:text-white tracking-tight">
              Render Desain Foto &amp; Story 9:16
            </h1>
            <p class="text-xs text-zinc-500 mt-1">Ekspor otomatis berita dan foto pilihan ke format grafis resolusi tinggi untuk Instagram Story, Feed, dan WhatsApp Status.</p>
          </div>
          <a href="/" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-zinc-700 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition shrink-0 self-start sm:self-auto border border-zinc-200 dark:border-zinc-700 shadow-xs">
            <svg class="w-3.5 h-3.5 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            <span>Kembali ke Beranda</span>
          </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          
          <!-- LEFT COLUMN: CREATOR CUSTOMIZATION CONTROLS (5 COLS) -->
          <div class="lg:col-span-5 space-y-4">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 sm:p-6 space-y-4 shadow-xs">
              
              <!-- Informasi Artikel Terpilih (Otomatis dari Artikel yang Dipilih) -->
              <div class="p-4 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 rounded-xl flex items-center justify-between gap-3 shadow-xs">
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] font-black uppercase text-[#CC0000] tracking-wider bg-red-100 dark:bg-red-900/60 px-2 py-0.5 rounded">
                      <?= htmlspecialchars($initialCategory) ?>
                    </span>
                    <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Artikel Terpilih</span>
                  </div>
                  <h4 class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-white line-clamp-2 leading-snug">
                    <?= htmlspecialchars($initialTitle) ?>
                  </h4>
                  <div class="text-[10px] text-zinc-500 mt-1">
                    Kredit Foto: <span class="font-semibold text-zinc-700 dark:text-zinc-300"><?= htmlspecialchars($initialCredit) ?></span>
                  </div>
                </div>
                <?php if (!empty($selectedRenderArticle['id'])): ?>
                  <a href="/<?= slugify($selectedRenderArticle['title']) ?>" class="text-[11px] font-bold text-[#CC0000] hover:underline shrink-0 flex items-center gap-1 bg-white dark:bg-zinc-800 px-3 py-1.5 rounded-lg border border-red-200 dark:border-red-900 shadow-xs">
                    <span>Lihat</span> &rarr;
                  </a>
                <?php endif; ?>
              </div>

              <!-- 2. Model Desain (Full Cover vs Split Card) -->
              <div>
                <label class="block text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300 mb-1.5">Model Desain Render:</label>
                <div class="grid grid-cols-2 gap-2">
                  <button type="button" id="btnLayoutFull" onclick="setLayout('full-cover')" class="p-2.5 text-left border rounded-xl transition cursor-pointer border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] font-bold">
                    <div class="text-xs font-black">Full Cover Gradasi</div>
                    <div class="text-[9px] opacity-75 mt-0.5">Foto Penuh + Teks Rata Kiri</div>
                  </button>
                  <button type="button" id="btnLayoutSplit" onclick="setLayout('split-card')" class="p-2.5 text-left border rounded-xl transition cursor-pointer border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    <div class="text-xs font-black">Split Card</div>
                    <div class="text-[9px] opacity-75 mt-0.5">Foto 2/3 &amp; Teks Rata Tengah</div>
                  </button>
                </div>
              </div>

              <!-- 3. Rasio Aspek (1:1, 4:5, 9:16) -->
              <div>
                <label class="block text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300 mb-1.5">Ukuran Rasio:</label>
                <div class="grid grid-cols-3 gap-2">
                  <button type="button" id="btnRatio11" onclick="setRatio('1:1')" class="p-2 text-center border rounded-xl transition cursor-pointer border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    <div class="text-sm font-black">1:1</div>
                    <div class="text-[9px] opacity-75">Persegi (Feed)</div>
                  </button>
                  <button type="button" id="btnRatio45" onclick="setRatio('4:5')" class="p-2 text-center border rounded-xl transition cursor-pointer border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    <div class="text-sm font-black">4:5</div>
                    <div class="text-[9px] opacity-75">Potret (IG)</div>
                  </button>
                  <button type="button" id="btnRatio916" onclick="setRatio('9:16')" class="p-2 text-center border rounded-xl transition cursor-pointer border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] font-bold">
                    <div class="text-sm font-black">9:16</div>
                    <div class="text-[9px] opacity-75">Story / WA</div>
                  </button>
                </div>
              </div>

              <!-- 4. Ukuran Font Judul -->
              <div>
                <label class="block text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300 mb-1.5">Ukuran Teks Judul:</label>
                <div class="grid grid-cols-3 gap-2">
                  <button type="button" id="btnFontSm" onclick="setFontSize('sm')" class="py-1.5 text-center border rounded-lg text-xs cursor-pointer border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50">Kecil</button>
                  <button type="button" id="btnFontMd" onclick="setFontSize('md')" class="py-1.5 text-center border rounded-lg text-xs cursor-pointer border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] font-bold">Sedang</button>
                  <button type="button" id="btnFontLg" onclick="setFontSize('lg')" class="py-1.5 text-center border rounded-lg text-xs cursor-pointer border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50">Besar</button>
                </div>
              </div>

              <!-- Tombol Unduh PNG Resolusi Tinggi -->
              <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <button type="button" id="btnDownloadRender" onclick="downloadRenderPNG()" class="w-full py-4 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition flex items-center justify-center gap-2 shadow-md cursor-pointer">
                  <svg class="w-4 h-4 fill-none stroke-current" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                  <span id="btnDownloadText">Unduh Desain (PNG Resolusi Tinggi)</span>
                </button>
              </div>

            </div>
          </div>

          <!-- RIGHT COLUMN: LIVE RENDER CANVAS (7 COLS) -->
          <div class="lg:col-span-7 flex flex-col items-center justify-center">
            
            <div class="text-xs text-zinc-500 font-bold mb-3 uppercase tracking-wider flex items-center gap-1.5">
              <span>👁️ Pratinjau Desain Langsung (Siap Ekspor)</span>
            </div>

            <!-- Outer Canvas Wrapper with Border Frame -->
            <div id="canvasOuterFrame" class="overflow-hidden rounded-2xl shadow-2xl border-4 border-zinc-800 dark:border-zinc-700 bg-black select-none transition-all w-[320px] sm:w-[360px] mx-auto" style="min-height: 568px;">
              
              <!-- Captured Target Container -->
              <div id="renderCanvasTarget" class="w-full overflow-hidden flex flex-col bg-black text-white relative shadow-2xl select-none" style="font-family: 'Plus Jakarta Sans', sans-serif; aspect-ratio: 9 / 16; min-height: 568px; position: relative;">

                <!-- Left: Logo Resmi GNN (Full 0px ke bagian atas frame) -->
                <div class="absolute top-0 left-5 z-30 select-none">
                  <img src="/icon/logow.png" alt="GNN" class="h-10 sm:h-11 w-auto object-contain object-top drop-shadow-md" onerror="this.onerror=null; this.src='/logow.png';">
                </div>

                <!-- Right: Badge Kategori Merah (Presisi optical dead-center) -->
                <div class="absolute top-3.5 right-5 z-30 select-none flex items-center justify-center">
                  <div style="background-color: #CC0000; border-radius: 9999px; height: 26px; padding: 0 14px; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.35); border: 1px solid rgba(255, 255, 255, 0.2);">
                    <span id="canvasCategoryBadge" style="color: #ffffff; font-weight: 800; font-size: 11px; letter-spacing: 0.05em; text-transform: uppercase; line-height: 1; transform: translateY(-1.5px); display: inline-block;">
                      <?= htmlspecialchars($initialCategory) ?>
                    </span>
                  </div>
                </div>

                <!-- CONTENT AREA: MODEL 1 (FULL COVER GRADASI) -->
                <div id="canvasLayoutFull" class="absolute inset-0 w-full h-full block">
                  <!-- Background Image with True Aspect Ratio Cover (No Stretching/Ceper in html2canvas) -->
                  <div id="canvasImageFull" class="absolute inset-0 w-full h-full z-0"
                       style="background-image: url('<?= htmlspecialchars($initialImage) ?>'); background-size: cover; background-position: center center; background-repeat: no-repeat;">
                  </div>

                  <!-- Top Vignette Overlay covering the canvas (NO 1/3 horizontal seam line!) -->
                  <div class="absolute inset-0 pointer-events-none z-10"
                       style="background: linear-gradient(to bottom, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.2) 20%, transparent 40%);">
                  </div>
                  
                  <!-- Bottom Overlay with Rich Smooth Gradient & Safe Bottom Padding -->
                  <div class="absolute inset-x-0 bottom-0 flex flex-col justify-end text-left z-20"
                       style="background: linear-gradient(to top, rgba(0,0,0,0.98) 0%, rgba(0,0,0,0.88) 45%, rgba(0,0,0,0.45) 75%, transparent 100%); padding-top: 100px; padding-bottom: 30px; padding-left: 24px; padding-right: 24px;">

                    <!-- Headline Title with Roboto Serif -->
                    <div class="w-full overflow-visible mb-4">
                      <h2 id="canvasTextFull" class="font-serif font-extrabold text-white tracking-tight leading-normal drop-shadow-lg text-xl sm:text-2xl text-left" style="font-family: 'Roboto Serif', Georgia, serif; line-height: 1.4; padding-bottom: 6px; display: block; overflow: visible;">
                        <?= htmlspecialchars($initialTitle) ?>
                      </h2>
                    </div>

                    <!-- Footer Meta Bar (Small clean domain and photo credit) -->
                    <div class="pt-3 border-t border-white/20 flex items-center justify-between text-[9px] text-zinc-300" style="line-height: 1.5;">
                      <div class="flex items-center gap-1 font-normal text-zinc-300/80 tracking-tight shrink-0 text-[8.5px] sm:text-[9px]">
                        <span class="text-[#CC0000] text-[7px]">●</span>
                        <span>www.goodnewsnusantara.my.id</span>
                      </div>
                      <div class="text-right text-zinc-400 font-normal pl-2 shrink-0 max-w-[65%] text-[8.5px] sm:text-[9px]" style="line-height: 1.5; padding-bottom: 2px;">
                        Foto: <span id="canvasCreditTextFull" class="text-zinc-200 font-medium"><?= htmlspecialchars($initialCredit) ?></span>
                      </div>
                    </div>

                  </div>
                </div>

                <!-- CONTENT AREA: MODEL 2 (SPLIT CARD) -->
                <div id="canvasLayoutSplit" class="absolute inset-0 w-full h-full hidden flex-col">
                  <!-- Image top (52%) with True Aspect Ratio Cover -->
                  <div class="relative w-full h-[52%] overflow-hidden bg-zinc-900 shrink-0">
                    <div id="canvasImageSplit" class="w-full h-full"
                         style="background-image: url('<?= htmlspecialchars($initialImage) ?>'); background-size: cover; background-position: center center; background-repeat: no-repeat;">
                    </div>
                    <!-- Top Vignette Overlay covering the ENTIRE top image (NO 1/3 horizontal seam line!) -->
                    <div class="absolute inset-0 pointer-events-none z-10"
                         style="background: linear-gradient(to bottom, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.2) 25%, transparent 50%);">
                    </div>
                  </div>
                  <!-- Content card bottom (48%) with generous space and no text clipping -->
                  <div class="relative w-full h-[48%] bg-[#0a0a0a] flex flex-col justify-between z-10 text-left"
                       style="padding-top: 22px; padding-bottom: 24px; padding-left: 24px; padding-right: 24px;">
                    <div class="w-full overflow-visible">
                      <h2 id="canvasTextSplit" class="font-serif font-extrabold text-white tracking-tight leading-normal drop-shadow-md text-base sm:text-lg" style="font-family: 'Roboto Serif', Georgia, serif; line-height: 1.45; padding-bottom: 8px; display: block; overflow: visible;">
                        <?= htmlspecialchars($initialTitle) ?>
                      </h2>
                    </div>

                    <!-- Footer Meta Bar -->
                    <div class="pt-3 border-t border-zinc-800 flex items-center justify-between text-[9px] text-zinc-400" style="line-height: 1.5;">
                      <div class="flex items-center gap-1 font-normal text-zinc-400 tracking-tight shrink-0 text-[8.5px] sm:text-[9px]">
                        <span class="text-[#CC0000] text-[7px]">●</span>
                        <span>www.goodnewsnusantara.my.id</span>
                      </div>
                      <div class="text-right text-zinc-400 font-normal pl-2 shrink-0 max-w-[65%] text-[8.5px] sm:text-[9px]" style="line-height: 1.5; padding-bottom: 2px;">
                        Foto: <span id="canvasCreditTextSplit" class="text-zinc-300 font-medium"><?= htmlspecialchars($initialCredit) ?></span>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

            </div>

          </div>

        </div>
      </div>

      <!-- SCRIPT LOGIC FOR RENDER CREATOR (NO NODE.JS REQUIRED) -->
      <script>
        let currentLayout = 'full-cover';
        let currentRatio = '9:16';
        let currentFontSize = 'md';

        function setLayout(layout) {
          currentLayout = layout;
          const isFull = (layout === 'full-cover');
          document.getElementById('canvasLayoutFull').style.display = isFull ? 'block' : 'none';
          document.getElementById('canvasLayoutSplit').style.display = isFull ? 'none' : 'flex';

          document.getElementById('btnLayoutFull').className = isFull
            ? 'p-2.5 text-left border rounded-xl transition cursor-pointer border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] font-bold'
            : 'p-2.5 text-left border rounded-xl transition cursor-pointer border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800';

          document.getElementById('btnLayoutSplit').className = !isFull
            ? 'p-2.5 text-left border rounded-xl transition cursor-pointer border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] font-bold'
            : 'p-2.5 text-left border rounded-xl transition cursor-pointer border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800';
        }

        function setRatio(ratio) {
          currentRatio = ratio;
          const frame = document.getElementById('canvasOuterFrame');
          const target = document.getElementById('renderCanvasTarget');

          if (ratio === '1:1') {
            target.style.aspectRatio = '1 / 1';
            target.style.minHeight = '360px';
            frame.style.width = '360px';
            frame.style.minHeight = '360px';
          } else if (ratio === '4:5') {
            target.style.aspectRatio = '4 / 5';
            target.style.minHeight = '450px';
            frame.style.width = '340px';
            frame.style.minHeight = '450px';
          } else {
            target.style.aspectRatio = '9 / 16';
            target.style.minHeight = '568px';
            frame.style.width = '320px';
            frame.style.minHeight = '568px';
          }

          ['1:1', '4:5', '9:16'].forEach(r => {
            const btnId = r === '1:1' ? 'btnRatio11' : r === '4:5' ? 'btnRatio45' : 'btnRatio916';
            const btn = document.getElementById(btnId);
            if (btn) {
              btn.className = (r === ratio)
                ? 'p-2 text-center border rounded-xl transition cursor-pointer border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] font-bold'
                : 'p-2 text-center border rounded-xl transition cursor-pointer border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800';
            }
          });
        }

        function setFontSize(size) {
          currentFontSize = size;
          const fullText = document.getElementById('canvasTextFull');
          const splitText = document.getElementById('canvasTextSplit');

          fullText.className = 'font-extrabold text-white tracking-tight leading-snug mb-4 text-left drop-shadow-lg ' +
            (size === 'sm' ? 'text-lg sm:text-xl' : size === 'lg' ? 'text-2xl sm:text-3xl' : 'text-xl sm:text-2xl');

          splitText.className = 'font-extrabold text-white tracking-tight leading-normal drop-shadow-md text-left ' +
            (size === 'sm' ? 'text-sm sm:text-base' : size === 'lg' ? 'text-lg sm:text-xl' : 'text-base sm:text-lg');

          ['sm', 'md', 'lg'].forEach(s => {
            const btnId = s === 'sm' ? 'btnFontSm' : s === 'md' ? 'btnFontMd' : 'btnFontLg';
            const btn = document.getElementById(btnId);
            if (btn) {
              btn.className = (s === size)
                ? 'py-1.5 text-center border rounded-lg text-xs cursor-pointer border-[#CC0000] bg-red-50 dark:bg-red-950/60 text-[#CC0000] font-bold'
                : 'py-1.5 text-center border rounded-lg text-xs cursor-pointer border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50';
            }
          });
        }

        async function downloadRenderPNG() {
          const btn = document.getElementById('btnDownloadRender');
          const btnText = document.getElementById('btnDownloadText');
          const target = document.getElementById('renderCanvasTarget');

          btn.disabled = true;
          btnText.textContent = 'Memproses Render Gambar...';

          if (document.fonts && document.fonts.ready) {
            try {
              await document.fonts.ready;
            } catch (e) {}
          }

          html2canvas(target, {
            scale: 3,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#000000',
            logging: false,
            scrollX: 0,
            scrollY: 0,
            imageTimeout: 15000
          }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'GNN-Desain-' + Date.now() + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            btn.disabled = false;
            btnText.textContent = 'Unduh Desain (PNG Resolusi Tinggi)';
          }).catch(err => {
            console.error('Error saat render:', err);
            alert('Gagal mengekspor gambar: ' + (err.message || err));
            btn.disabled = false;
            btnText.textContent = 'Unduh Desain (PNG Resolusi Tinggi)';
          });
        }
      </script>

    <?php elseif ($viewParam === 'tv'): ?>
      <!-- ========================================== -->
      <!-- GNN TV / VIDEO NUSANTARA -->
      <!-- ========================================== -->
      <section class="mb-10">
        <div class="border-b border-zinc-200 pb-4 mb-6 flex items-center justify-between">
          <div>
            <h1 class="font-serif font-black text-3xl text-zinc-900">GNN TV & Video Nusantara</h1>
            <p class="text-xs text-zinc-500 mt-1">Dokumenter, inovasi, dan kabar kebanggaan nusantara berformat audio visual.</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($videos as $v): ?>
            <div class="bg-white rounded-xl overflow-hidden border border-zinc-200 shadow-xs flex flex-col">
              <div class="aspect-video w-full bg-black relative">
                <iframe class="w-full h-full" src="https://www.youtube-nocookie.com/embed/<?= htmlspecialchars($v['youtube_id']) ?>" title="<?= htmlspecialchars($v['title']) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
              </div>
              <div class="p-4 flex-1 flex flex-col justify-between">
                <div>
                  <span class="text-[10px] font-bold text-red-700 uppercase"><?= htmlspecialchars($v['category'] ?? 'VIDEO') ?></span>
                  <h3 class="font-bold text-sm text-zinc-900 line-clamp-2 mt-1 mb-2"><?= htmlspecialchars($v['title']) ?></h3>
                  <p class="text-xs text-zinc-600 line-clamp-2"><?= htmlspecialchars($v['description'] ?? '') ?></p>
                </div>
                <div class="mt-4 pt-3 border-t border-zinc-100 text-[11px] text-zinc-400 flex justify-between">
                  <span><?= htmlspecialchars($v['author'] ?? 'Tim Kreatif GNN') ?></span>
                  <span><?= htmlspecialchars($v['duration'] ?? '04:15') ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

    <?php elseif ($viewParam === 'gallery'): ?>
      <!-- ========================================== -->
      <!-- GALERI FOTO NUSANTARA -->
      <!-- ========================================== -->
      <section class="mb-10">
        <div class="border-b border-zinc-200 pb-4 mb-6">
          <h1 class="font-serif font-black text-3xl text-zinc-900">Galeri Dokumentasi Nusantara</h1>
          <p class="text-xs text-zinc-500 mt-1">Rekam jejak keindahan alam, budaya, dan kiprah anak bangsa dari lensa Good News Nusantara.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <?php foreach ($gallery as $gal): ?>
            <div class="bg-white rounded-xl overflow-hidden border border-zinc-200 shadow-xs group">
              <div class="overflow-hidden w-full bg-zinc-100" style="aspect-ratio: 4 / 3;">
                <img src="<?= htmlspecialchars($gal['image_url']) ?>" alt="<?= htmlspecialchars($gal['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
              </div>
              <div class="p-3">
                <h4 class="font-bold text-xs text-zinc-900 line-clamp-1"><?= htmlspecialchars($gal['title']) ?></h4>
                <p class="text-[10px] text-zinc-500 line-clamp-1 mt-0.5"><?= htmlspecialchars($gal['author'] ?? 'Pengguna GNN') ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

    <?php elseif ($pageParam): ?>
      <!-- ========================================== -->
      <!-- STATIC PAGES: PEDOMAN, ATURAN, PRIVASI, DLL -->
      <!-- ========================================== -->
      <div class="max-w-5xl mx-auto py-4 sm:py-8">
        
        <!-- Header Hero Banner -->
        <div class="relative overflow-hidden rounded-3xl p-6 sm:p-10 shadow-xl mb-6 sm:mb-8 border border-zinc-800" style="background: linear-gradient(135deg, #09090b 0%, #18181b 50%, #450a0a 100%); color: #ffffff;">
          <div class="relative z-10 max-w-3xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-red-500/40 text-[11px] font-bold uppercase tracking-wider mb-4" style="background-color: rgba(220, 38, 38, 0.25); color: #fecaca;">
              <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
              Pusat Regulasi &amp; Informasi Publik GNN
            </div>
            <h1 class="font-serif font-black text-2xl sm:text-4xl tracking-tight leading-tight mb-3" style="color: #ffffff;">
              <?php
                if ($pageParam === 'pedoman') echo 'Pedoman Pemberitaan Media Siber';
                elseif ($pageParam === 'aturan') echo 'Aturan &amp; Ketentuan Layanan';
                elseif ($pageParam === 'privasi') echo 'Kebijakan Privasi';
                elseif ($pageParam === 'redaksi') echo 'Susunan Dewan Redaksi';
                elseif ($pageParam === 'tentang') echo 'Tentang Good News Nusantara';
                else echo 'Kontak Iklan &amp; Kerjasama Redaksi';
              ?>
            </h1>
            <p class="text-xs sm:text-sm leading-relaxed" style="color: #d4d4d8;">
              Standar profesionalisme, kode etik pers, kepatuhan hukum, dan transparansi Good News Nusantara dalam menyebarkan kabar baik demi membangun optimisme bangsa.
            </p>
          </div>
          <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-red-600/10 rounded-full blur-3xl pointer-events-none"></div>
        </div>

        <!-- Pill Navigation Bar -->
        <div class="mb-8 flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none no-scrollbar">
          <a href="?page=pedoman" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition <?= $pageParam === 'pedoman' ? 'bg-red-600 text-white shadow-md shadow-red-600/30' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' ?>">
            <span>📜</span> Pedoman Media Siber
          </a>
          <a href="?page=aturan" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition <?= $pageParam === 'aturan' ? 'bg-red-600 text-white shadow-md shadow-red-600/30' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' ?>">
            <span>⚖️</span> Aturan &amp; Ketentuan
          </a>
          <a href="?page=privasi" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition <?= $pageParam === 'privasi' ? 'bg-red-600 text-white shadow-md shadow-red-600/30' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' ?>">
            <span>🛡️</span> Kebijakan Privasi
          </a>
          <a href="?page=redaksi" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition <?= $pageParam === 'redaksi' ? 'bg-red-600 text-white shadow-md shadow-red-600/30' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' ?>">
            <span>👥</span> Susunan Redaksi
          </a>
          <a href="?page=tentang" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition <?= $pageParam === 'tentang' ? 'bg-red-600 text-white shadow-md shadow-red-600/30' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' ?>">
            <span>🇮🇩</span> Tentang Kami
          </a>
          <a href="?page=kontak" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition <?= $pageParam === 'kontak' ? 'bg-red-600 text-white shadow-md shadow-red-600/30' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' ?>">
            <span>📬</span> Kontak &amp; Iklan
          </a>
        </div>

        <!-- Main Card Content -->
        <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 p-6 sm:p-10 shadow-xs">
          
          <?php if ($pageParam === 'pedoman'): ?>
            <!-- ================= PEDOMAN PEMBERITAAN MEDIA SIBER ================= -->
            <div class="space-y-8">
              
              <!-- Preamble Callout -->
              <div class="p-5 sm:p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/60 border-l-4 border-red-600 text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed space-y-3">
                <p class="font-bold text-zinc-900 dark:text-white">Landasan Kemerdekaan Pers &amp; Pengelolaan Profesional</p>
                <p>
                  Kemerdekaan berpendapat, kemerdekaan berekspresi, dan kemerdekaan pers adalah hak asasi manusia yang dilindungi Pancasila, Undang-Undang Dasar 1945, dan Deklarasi Universal Hak Asasi Manusia PBB. Keberadaan media siber di Indonesia juga merupakan bagian dari kemerdekaan berpendapat, kemerdekaan berekspresi, dan kemerdekaan pers.
                </p>
                <p>
                  Media siber memiliki karakter khusus sehingga memerlukan pedoman agar pengelolaannya dapat dilaksanakan secara profesional, memenuhi fungsi, hak, dan kewajibannya sesuai Undang-Undang Nomor 40 Tahun 1999 tentang Pers dan Kode Etik Jurnalistik. Untuk itu Dewan Pers bersama organisasi pers, pengelola media siber, dan masyarakat menyusun Pedoman Pemberitaan Media Siber sebagai berikut:
                </p>
              </div>

              <!-- Point 1 -->
              <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                <div class="flex items-start gap-4">
                  <span class="flex-shrink-0 w-8 h-8 rounded-full bg-red-600 text-white font-black text-sm flex items-center justify-center shadow-xs">1</span>
                  <div class="space-y-3 flex-1">
                    <h3 class="font-bold text-base text-zinc-900 dark:text-white">Ruang Lingkup</h3>
                    <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                      <li><strong>a. Media Siber</strong> adalah segala bentuk media yang menggunakan wahana internet dan melaksanakan kegiatan jurnalistik, serta memenuhi persyaratan Undang-Undang Pers dan Standar Perusahaan Pers yang ditetapkan Dewan Pers.</li>
                      <li><strong>b. Isi Buatan Pengguna (User Generated Content)</strong> adalah segala isi yang dibuat dan atau dipublikasikan oleh pengguna media siber, antara lain, artikel, gambar, komentar, suara, video dan berbagai bentuk unggahan yang melekat pada media siber, seperti blog, forum, komentar pembaca atau pemirsa, dan bentuk lain.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Point 2 -->
              <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                <div class="flex items-start gap-4">
                  <span class="flex-shrink-0 w-8 h-8 rounded-full bg-red-600 text-white font-black text-sm flex items-center justify-center shadow-xs">2</span>
                  <div class="space-y-3 flex-1">
                    <h3 class="font-bold text-base text-zinc-900 dark:text-white">Verifikasi dan Keberimbangan Berita</h3>
                    <ul class="space-y-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                      <li><strong>a.</strong> Pada prinsipnya setiap berita harus melalui verifikasi.</li>
                      <li><strong>b.</strong> Berita yang dapat merugikan pihak lain memerlukan verifikasi pada berita yang sama untuk memenuhi prinsip akurasi dan keberimbangan.</li>
                      <li><strong>c.</strong> Ketentuan dalam butir (a) di atas dikecualikan, dengan syarat:
                        <div class="mt-2 pl-4 space-y-1.5 border-l-2 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400">
                          <p>1) Berita benar-benar mengandung kepentingan publik yang bersifat mendesak;</p>
                          <p>2) Sumber berita yang pertama adalah sumber yang jelas disebutkan identitasnya, kredibel dan kompeten;</p>
                          <p>3) Subyek berita yang harus dikonfirmasi tidak diketahui keberadaannya dan atau tidak dapat diwawancarai;</p>
                          <p>4) Media memberikan penjelasan kepada pembaca bahwa berita tersebut masih memerlukan verifikasi lebih lanjut yang diupayakan dalam waktu secepatnya. Penjelasan dimuat pada bagian akhir dari berita yang sama, di dalam kurung dan menggunakan huruf miring.</p>
                        </div>
                      </li>
                      <li><strong>d.</strong> Setelah memuat berita sesuai dengan butir (c), media wajib meneruskan upaya verifikasi, dan setelah verifikasi didapatkan, hasil verifikasi dicantumkan pada berita pemutakhiran (update) dengan tautan pada berita yang belum terverifikasi.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Point 3 -->
              <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                <div class="flex items-start gap-4">
                  <span class="flex-shrink-0 w-8 h-8 rounded-full bg-red-600 text-white font-black text-sm flex items-center justify-center shadow-xs">3</span>
                  <div class="space-y-3 flex-1">
                    <h3 class="font-bold text-base text-zinc-900 dark:text-white">Isi Buatan Pengguna (User Generated Content)</h3>
                    <ul class="space-y-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                      <li><strong>a.</strong> Media siber wajib mencantumkan syarat dan ketentuan mengenai Isi Buatan Pengguna yang tidak bertentangan dengan Undang-Undang No. 40 tahun 1999 tentang Pers dan Kode Etik Jurnalistik, yang ditempatkan secara terang dan jelas.</li>
                      <li><strong>b.</strong> Media siber mewajibkan setiap pengguna untuk melakukan registrasi keanggotaan dan melakukan proses log-in terlebih dahulu untuk dapat mempublikasikan semua bentuk Isi Buatan Pengguna. Ketentuan mengenai log-in akan diatur lebih lanjut.</li>
                      <li><strong>c.</strong> Dalam registrasi tersebut, media siber mewajibkan pengguna memberi persetujuan tertulis bahwa Isi Buatan Pengguna yang dipublikasikan:
                        <div class="mt-2 pl-4 space-y-1 border-l-2 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400">
                          <p>1) Tidak memuat isi bohong, fitnah, sadis dan cabul;</p>
                          <p>2) Tidak memuat isi yang mengandung prasangka dan kebencian terkait dengan suku, agama, ras, dan antargolongan (SARA), serta menganjurkan tindakan kekerasan;</p>
                          <p>3) Tidak memuat isi diskriminatif atas dasar perbedaan jenis kelamin dan bahasa, serta tidak merendahkan martabat orang lemah, miskin, sakit, cacat jiwa, atau cacat jasmani.</p>
                        </div>
                      </li>
                      <li><strong>d.</strong> Media siber memiliki kewenangan mutlak untuk mengedit atau menghapus Isi Buatan Pengguna yang bertentangan dengan butir (c).</li>
                      <li><strong>e.</strong> Media siber wajib menyediakan mekanisme pengaduan Isi Buatan Pengguna yang dinilai melanggar ketentuan pada butir (c). Mekanisme tersebut harus disediakan di tempat yang dengan mudah dapat diakses pengguna.</li>
                      <li><strong>f.</strong> Media siber wajib menyunting, menghapus, dan melakukan tindakan koreksi setiap Isi Buatan Pengguna yang dilaporkan dan melanggar ketentuan butir (c), sesegera mungkin secara proporsional selambat-lambatnya 2 x 24 jam setelah pengaduan diterima.</li>
                      <li><strong>g.</strong> Media siber yang telah memenuhi ketentuan pada butir (a), (b), (c), dan (f) tidak dibebani tanggung jawab atas masalah yang ditimbulkan akibat pemuatan isi yang melanggar ketentuan pada butir (c).</li>
                      <li><strong>h.</strong> Media siber bertanggung jawab atas Isi Buatan Pengguna yang dilaporkan bila tidak mengambil tindakan koreksi setelah batas waktu sebagaimana tersebut pada butir (f).</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Point 4 -->
              <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                <div class="flex items-start gap-4">
                  <span class="flex-shrink-0 w-8 h-8 rounded-full bg-red-600 text-white font-black text-sm flex items-center justify-center shadow-xs">4</span>
                  <div class="space-y-3 flex-1">
                    <h3 class="font-bold text-base text-zinc-900 dark:text-white">Ralat, Koreksi, dan Hak Jawab</h3>
                    <ul class="space-y-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                      <li><strong>a.</strong> Ralat, koreksi, dan hak jawab mengacu pada Undang-Undang Pers, Kode Etik Jurnalistik, dan Pedoman Hak Jawab yang ditetapkan Dewan Pers.</li>
                      <li><strong>b.</strong> Ralat, koreksi dan atau hak jawab wajib ditautkan pada berita yang diralat, dikoreksi atau yang diberi hak jawab.</li>
                      <li><strong>c.</strong> Di setiap berita ralat, koreksi, dan hak jawab wajib dicantumkan waktu pemuatan ralat, koreksi, dan atau hak jawab tersebut.</li>
                      <li><strong>d.</strong> Bila suatu berita media siber tertentu disebarluaskan media siber lain, maka:
                        <div class="mt-2 pl-4 space-y-1 border-l-2 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400">
                          <p>1) Tanggung jawab media siber pembuat berita terbatas pada berita yang dipublikasikan di media siber tersebut atau media siber yang berada di bawah otoritas teknisnya;</p>
                          <p>2) Koreksi berita yang dilakukan oleh sebuah media siber, juga harus dilakukan oleh media siber lain yang mengutip berita dari media siber yang dikoreksi itu;</p>
                          <p>3) Media yang menyebarluaskan berita dari sebuah media siber dan tidak melakukan koreksi atas berita sesuai yang dilakukan oleh media siber pemilik dan atau pembuat berita tersebut, bertanggung jawab penuh atas semua akibat hukum dari berita yang tidak dikoreksinya itu.</p>
                        </div>
                      </li>
                      <li><strong>e.</strong> Sesuai dengan Undang-Undang Pers, media siber yang tidak melayani hak jawab dapat dijatuhi sanksi hukum pidana denda paling banyak <strong>Rp500.000.000 (Lima ratus juta rupiah)</strong>.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Point 5 -->
              <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                <div class="flex items-start gap-4">
                  <span class="flex-shrink-0 w-8 h-8 rounded-full bg-red-600 text-white font-black text-sm flex items-center justify-center shadow-xs">5</span>
                  <div class="space-y-3 flex-1">
                    <h3 class="font-bold text-base text-zinc-900 dark:text-white">Pencabutan Berita</h3>
                    <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                      <li><strong>a.</strong> Berita yang sudah dipublikasikan tidak dapat dicabut karena alasan penyensoran dari pihak luar redaksi, kecuali terkait masalah SARA, kesusilaan, masa depan anak, pengalaman traumatik korban atau berdasarkan pertimbangan khusus lain yang ditetapkan Dewan Pers.</li>
                      <li><strong>b.</strong> Media siber lain wajib mengikuti pencabutan kutipan berita dari media asal yang telah dicabut.</li>
                      <li><strong>c.</strong> Pencabutan berita wajib disertai dengan alasan pencabutan dan diumumkan kepada publik.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Point 6 -->
              <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                <div class="flex items-start gap-4">
                  <span class="flex-shrink-0 w-8 h-8 rounded-full bg-red-600 text-white font-black text-sm flex items-center justify-center shadow-xs">6</span>
                  <div class="space-y-3 flex-1">
                    <h3 class="font-bold text-base text-zinc-900 dark:text-white">Iklan</h3>
                    <ul class="space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                      <li><strong>a.</strong> Media siber wajib membedakan dengan tegas antara produk berita dan iklan.</li>
                      <li><strong>b.</strong> Setiap berita/artikel/isi yang merupakan iklan dan atau isi berbayar wajib mencantumkan keterangan <em>'advertorial'</em>, <em>'iklan'</em>, <em>'ads'</em>, <em>'sponsored'</em>, atau kata lain yang menjelaskan bahwa berita/artikel/isi tersebut adalah iklan.</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Point 7, 8, 9 -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-5 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2">
                  <span class="w-7 h-7 rounded-full bg-red-600 text-white font-black text-xs flex items-center justify-center">7</span>
                  <h4 class="font-bold text-sm text-zinc-900 dark:text-white">Hak Cipta</h4>
                  <p class="text-xs text-zinc-600 dark:text-zinc-400">Media siber wajib menghormati hak cipta sebagaimana diatur dalam peraturan perundang-undangan yang berlaku.</p>
                </div>
                <div class="p-5 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2">
                  <span class="w-7 h-7 rounded-full bg-red-600 text-white font-black text-xs flex items-center justify-center">8</span>
                  <h4 class="font-bold text-sm text-zinc-900 dark:text-white">Pencantuman Pedoman</h4>
                  <p class="text-xs text-zinc-600 dark:text-zinc-400">Media siber wajib mencantumkan Pedoman Pemberitaan Media Siber ini di medianya secara terang dan jelas.</p>
                </div>
                <div class="p-5 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2">
                  <span class="w-7 h-7 rounded-full bg-red-600 text-white font-black text-xs flex items-center justify-center">9</span>
                  <h4 class="font-bold text-sm text-zinc-900 dark:text-white">Sengketa</h4>
                  <p class="text-xs text-zinc-600 dark:text-zinc-400">Penilaian akhir atas sengketa mengenai pelaksanaan Pedoman Pemberitaan Media Siber ini diselesaikan oleh Dewan Pers.</p>
                </div>
              </div>

              <!-- Signoff Box -->
              <div class="p-4 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-center text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                Jakarta, 3 Februari 2012 &bull; Ditandatangani oleh Dewan Pers dan komunitas pers di Jakarta
              </div>

            </div>

          <?php elseif ($pageParam === 'aturan'): ?>
            <!-- ================= ATURAN DI GOOD NEWS NUSANTARA ================= -->
            <div class="space-y-8">
              
              <div class="p-5 sm:p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/60 border-l-4 border-red-600 text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed space-y-3">
                <p class="font-bold text-zinc-900 dark:text-white">Ketentuan Umum Penggunaan Layanan</p>
                <p>
                  Dengan mengakses laman <strong>Good News Nusantara (GNN)</strong> (termasuk berbagai fitur, studio, ceritera, dan produk di dalamnya melalui domain <a href="https://gnn.web.id" class="text-red-600 underline">https://gnn.web.id</a>), Anda dianggap telah menyetujui batasan hukum yang telah ditentukan untuk situs ini. Batasan-batasan tersebut dapat dimodifikasi secara berkala sesuai dengan perkembangan platform dan regulasi pers nasional.
                </p>
                <p>
                  Oleh karena itu, Anda diminta untuk membaca Ketentuan Penggunaan dan Kebijakan Privasi sebelum mengakses situs ini serta terus memantau perubahan berkala yang kami berlakukan.
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Penggunaan Situs -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                  <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 flex items-center justify-center font-bold text-lg">🌐</div>
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white">Penggunaan Situs</h3>
                  <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">
                    Situs ini dimiliki dan dikelola oleh <strong>Good News Nusantara</strong>. Kami berhak menangguhkan, membatasi, atau menghentikan akses Anda ke situs ini setiap saat, dengan atau tanpa pemberitahuan atau penjelasan, apabila terindikasi melanggar hukum atau pedoman media siber.
                  </p>
                </div>

                <!-- Hak Kekayaan Intelektual -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                  <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 flex items-center justify-center font-bold text-lg">🛡️</div>
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white">Hak-Hak &amp; Kekayaan Intelektual</h3>
                  <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">
                    Semua hak kekayaan intelektual dalam isi, termasuk hak cipta, dapat diakses di situs ini. Kekayaan intelektual dalam bentuk teks, foto, grafik, video, materi audio, perangkat lunak, maupun bentuk lainnya merupakan milik Good News Nusantara kecuali konten yang dinyatakan berlisensi Creative Commons (CC) atau atribusi pemilik asal.
                  </p>
                </div>

                <!-- Mengutip & Berbagi -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                  <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 flex items-center justify-center font-bold text-lg">🔗</div>
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white">Penggunaan Isi, Mengutip, &amp; Berbagi</h3>
                  <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">
                    Situs ini dan seluruh isi di dalamnya hanya boleh digunakan untuk keperluan pribadi dan edukatif, bukan untuk keperluan komersil tanpa izin tertulis. Anda diperkenankan mengutip sebagian konten di media sosial atau blog sepanjang mencantumkan sumber dan <strong>menyisipkan back-link atau tautan balik langsung</strong> ke halaman spesifik konten di situs Good News Nusantara.
                  </p>
                </div>

                <!-- Konten Buatan Pengguna -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                  <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 flex items-center justify-center font-bold text-lg">✍️</div>
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white">Konten Buatan Pengguna (UGC)</h3>
                  <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">
                    Dengan mengirimkan materi (teks, foto, video, grafik) ke Studio atau kanal Good News Nusantara, Anda memberikan hak lisensi non-eksklusif dan bebas royalti kepada kami untuk mempublikasikan dan menyebarkannya. Anda menjamin bahwa materi tersebut adalah orisinal, tidak melanggar hak cipta pihak ketiga, dan tidak melanggar hukum RI.
                  </p>
                </div>

                <!-- Penolakan Tanggung Jawab -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                  <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 flex items-center justify-center font-bold text-lg">⚠️</div>
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white">Penolakan &amp; Batasan Tanggung Jawab</h3>
                  <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">
                    Kami senantiasa berusaha menyajikan konten yang akurat, terverifikasi, dan bermutu tinggi. Namun Good News Nusantara tidak bertanggung jawab atas kerugian langsung atau tidak langsung akibat pemanfaatan konten di luar tujuan jurnalistik dan referensi publik.
                  </p>
                </div>

                <!-- Ganti Rugi & Hukum -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                  <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 flex items-center justify-center font-bold text-lg">⚖️</div>
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white">Ganti Rugi &amp; Hukum yang Berlaku</h3>
                  <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">
                    Pengguna menyetujui untuk melindungi dan mengganti kerugian kepada Good News Nusantara dari segala gugatan akibat pelanggaran terhadap ketentuan ini. Segala sengketa diatur dan diselesaikan berdasarkan hukum positif Negara Kesatuan Republik Indonesia.
                  </p>
                </div>

              </div>

            </div>

          <?php elseif ($pageParam === 'privasi'): ?>
            <!-- ================= KEBIJAKAN PRIVASI GOOD NEWS NUSANTARA ================= -->
            <div class="space-y-8">
              
              <div class="p-5 sm:p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/60 border-l-4 border-red-600 text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed space-y-3">
                <p class="font-bold text-zinc-900 dark:text-white">Komitmen Perlindungan Privasi Pengunjung</p>
                <p>
                  Di <strong>Good News Nusantara</strong>, dapat diakses dari <a href="https://gnn.web.id" class="text-red-600 underline">https://gnn.web.id</a>, salah satu prioritas utama kami adalah privasi para pengunjung kami. Dokumen Kebijakan Privasi ini berisi jenis informasi yang dikumpulkan dan dicatat oleh Good News Nusantara serta bagaimana kami menggunakannya.
                </p>
                <p>
                  Jika Anda memiliki pertanyaan tambahan atau memerlukan informasi lebih lanjut tentang Kebijakan Privasi kami, jangan ragu untuk menghubungi kami melalui surat elektronik di <strong>redaksi@gnn.web.id</strong>.
                </p>
              </div>

              <div class="space-y-6">

                <!-- Berkas Log -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                    <span class="text-red-600 font-bold">#</span> Berkas Log (Log Files)
                  </h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">
                    Good News Nusantara mengikuti prosedur standar penggunaan file log. File-file ini mencatat pengunjung ketika mereka mengunjungi situs web. Semua perusahaan hosting melakukan ini sebagai bagian dari analitik layanan hosting. Informasi yang dikumpulkan oleh file log meliputi alamat protokol internet (IP), jenis peramban (browser), Penyedia Layanan Internet (ISP), tanggal dan waktu, halaman perujuk/keluar (referring/exit pages), dan kemungkinan jumlah klik. Informasi ini tidak dikaitkan dengan informasi pribadi apa pun yang dapat diidentifikasi dan ditujukan semata-mata untuk menganalisis tren dan mengelola situs.
                  </p>
                </div>

                <!-- Cookies & Web Beacon -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                    <span class="text-red-600 font-bold">#</span> Cookie dan Web Beacon
                  </h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">
                    Seperti situs web lainnya, Good News Nusantara menggunakan 'cookies'. Cookie ini digunakan untuk menyimpan informasi termasuk preferensi pengunjung, dan halaman-halaman di situs web yang diakses atau dikunjungi pengunjung. Informasi tersebut digunakan untuk mengoptimalkan pengalaman pengguna dengan menyesuaikan konten halaman web kami berdasarkan jenis peramban pengunjung dan/atau informasi lainnya.
                  </p>
                </div>

                <!-- Cookie Google DoubleClick DART -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                    <span class="text-red-600 font-bold">#</span> Cookie Google DoubleClick DART
                  </h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">
                    Google adalah salah satu vendor pihak ketiga di situs kami. Google juga menggunakan cookie, yang dikenal sebagai cookie DART, untuk menayangkan iklan kepada pengunjung situs kami berdasarkan kunjungan mereka ke https://gnn.web.id dan situs-situs lain di internet. Namun, pengunjung dapat memilih untuk menolak penggunaan cookie DART dengan mengunjungi Kebijakan Privasi jaringan iklan dan konten Google di tautan berikut: <a href="https://policies.google.com/technologies/ads" target="_blank" rel="noopener noreferrer" class="text-red-600 underline">https://policies.google.com/technologies/ads</a>.
                  </p>
                </div>

                <!-- Kebijakan Privasi Pihak Ketiga -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                    <span class="text-red-600 font-bold">#</span> Kebijakan Privasi Pihak Ketiga
                  </h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">
                    Kebijakan Privasi Good News Nusantara tidak berlaku untuk pengiklan atau situs web lain. Karena itu, kami menyarankan Anda untuk berkonsultasi dengan Kebijakan Privasi masing-masing dari server iklan pihak ketiga ini untuk informasi yang lebih rinci. Ini mungkin mencakup praktik dan instruksi mereka tentang cara memilih keluar dari opsi tertentu.
                  </p>
                </div>

                <!-- Hak Privasi Pengguna (GDPR & CCPA) -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                    <span class="text-red-600 font-bold">#</span> Hak Privasi Konsumen &amp; Perlindungan Data
                  </h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">
                    Kami ingin memastikan Anda mengetahui hak perlindungan data Anda. Setiap pengguna berhak atas:
                  </p>
                  <ul class="list-disc pl-5 text-sm text-zinc-600 dark:text-zinc-400 space-y-1">
                    <li><strong>Hak untuk mengakses:</strong> Anda berhak meminta salinan data pribadi Anda.</li>
                    <li><strong>Hak untuk perbaikan:</strong> Anda berhak meminta kami memperbaiki informasi yang Anda yakini tidak akurat.</li>
                    <li><strong>Hak untuk penghapusan:</strong> Anda berhak meminta kami menghapus data pribadi Anda dalam kondisi tertentu.</li>
                    <li><strong>Hak untuk membatasi pemrosesan:</strong> Anda berhak meminta pembatasan pemrosesan data pribadi Anda.</li>
                  </ul>
                  <p class="text-xs text-zinc-500 pt-1">Jika Anda mengajukan permintaan, kami memiliki waktu satu bulan untuk menanggapi Anda. Silakan hubungi kami di <strong>redaksi@gnn.web.id</strong>.</p>
                </div>

                <!-- Informasi Anak-Anak -->
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                    <span class="text-red-600 font-bold">#</span> Informasi Perlindungan Anak
                  </h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">
                    Bagian lain dari prioritas kami adalah menambahkan perlindungan bagi anak-anak saat menggunakan internet. Kami mendorong orang tua dan wali untuk mengamati, berpartisipasi, dan/atau memantau aktivitas daring mereka. Good News Nusantara tidak dengan sengaja mengumpulkan Informasi Identifikasi Pribadi apa pun dari anak-anak di bawah usia 13 tahun.
                  </p>
                </div>

                <!-- Persetujuan -->
                <div class="p-5 rounded-2xl bg-zinc-100 dark:bg-zinc-800/80 text-zinc-800 dark:text-zinc-200 text-sm">
                  <strong>Persetujuan:</strong> Dengan menggunakan situs web kami (<a href="https://gnn.web.id" class="text-red-600 font-semibold underline">https://gnn.web.id</a>), Anda dengan ini menyetujui Kebijakan Privasi kami dan menyetujui syarat-syaratnya.
                </div>

              </div>

            </div>

          <?php elseif ($pageParam === 'redaksi'): ?>
            <!-- ================= SUSUNAN DEWAN REDAKSI ================= -->
            <div class="space-y-8">
              
              <div class="p-5 sm:p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/60 border-l-4 border-red-600 text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed">
                Good News Nusantara dikelola secara independen oleh para jurnalis, kurator kabar baik, dan kreator multimedia dengan dedikasi tinggi terhadap integritas pers dan optimisme kebangsaan.
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2">
                  <span class="text-[10px] font-bold tracking-wider uppercase text-red-600 bg-red-50 dark:bg-red-950/50 px-2.5 py-1 rounded-full">PIMPINAN</span>
                  <h3 class="font-bold text-lg text-zinc-900 dark:text-white">Pemimpin Umum / Penanggung Jawab</h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 font-medium">Fadhil Al Anshor</p>
                  <p class="text-xs text-zinc-500">Penanggung Jawab Kebijakan Redaksional &amp; Korporasi</p>
                </div>

                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2">
                  <span class="text-[10px] font-bold tracking-wider uppercase text-red-600 bg-red-50 dark:bg-red-950/50 px-2.5 py-1 rounded-full">EDITORIAL</span>
                  <h3 class="font-bold text-lg text-zinc-900 dark:text-white">Pemimpin Redaksi</h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 font-medium">Dewan Redaksi Good News Nusantara</p>
                  <p class="text-xs text-zinc-500">Pengarah Konten Jurnalistik, Verifikasi &amp; Standar Kode Etik</p>
                </div>

                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2">
                  <span class="text-[10px] font-bold tracking-wider uppercase text-red-600 bg-red-50 dark:bg-red-950/50 px-2.5 py-1 rounded-full">DESK PELAKSANA</span>
                  <h3 class="font-bold text-lg text-zinc-900 dark:text-white">Redaktur Pelaksana &amp; Kanal Khusus</h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 font-medium">Desk Inovasi, Sejarah, Sains &amp; Ekonomi Kreatif</p>
                  <p class="text-xs text-zinc-500">Kurasi Berita Nusantara, Prestasi Anak Bangsa &amp; Wisata</p>
                </div>

                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2">
                  <span class="text-[10px] font-bold tracking-wider uppercase text-red-600 bg-red-50 dark:bg-red-950/50 px-2.5 py-1 rounded-full">MULTIMEDIA &amp; STUDIO</span>
                  <h3 class="font-bold text-lg text-zinc-900 dark:text-white">GNN TV &amp; Studio Multimedia</h3>
                  <p class="text-sm text-zinc-700 dark:text-zinc-300 font-medium">Tim Kreatif &amp; Fotografi Ceritera</p>
                  <p class="text-xs text-zinc-500">Produksi Visual, Story 9:16 &amp; Dokumenter Budaya</p>
                </div>

              </div>

              <!-- Kontak Box -->
              <div class="p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700 space-y-3">
                <h4 class="font-bold text-sm text-zinc-900 dark:text-white uppercase tracking-wider">Sekretariat &amp; Alamat Redaksi</h4>
                <div class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-300 space-y-1.5">
                  <p><strong>Alamat:</strong> Gedung Redaksi Good News Nusantara, Jakarta, Indonesia</p>
                  <p><strong>Surat Elektronik:</strong> <a href="mailto:redaksi@gnn.web.id" class="text-red-600 underline">redaksi@gnn.web.id</a> | <a href="mailto:admin@gnn.id" class="text-red-600 underline">admin@gnn.id</a></p>
                  <p><strong>Situs Resmi:</strong> <a href="https://gnn.web.id" class="text-red-600 underline">https://gnn.web.id</a></p>
                </div>
              </div>

            </div>

          <?php elseif ($pageParam === 'tentang'): ?>
            <!-- ================= TENTANG GOOD NEWS NUSANTARA ================= -->
            <div class="space-y-8">
              
              <div class="p-5 sm:p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/60 border-l-4 border-red-600 text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed space-y-3">
                <p class="font-bold text-zinc-900 dark:text-white text-base">Jadi Tahu Indonesia: Mengabarkan Potensi, Merajut Harapan</p>
                <p>
                  <strong>Good News Nusantara (GNN)</strong> adalah platform media digital independen yang berdedikasi menyebarluaskan kabar baik, inovasi, prestasi generasi muda, keanekaragaman budaya, kekayaan alam, serta keindahan sejarah Nusantara.
                </p>
                <p>
                  Kami meyakini bahwa narasi positif dan optimisme adalah energi pendorong utama bagi kemajuan bangsa. Di tengah banjir informasi digital, GNN hadir sebagai ruang jernih yang mengedukasi, menginspirasi, dan menumbuhkan rasa bangga terhadap Indonesia.
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                  <div class="text-2xl">🌱</div>
                  <h4 class="font-bold text-base text-zinc-900 dark:text-white">Misi Edukasi</h4>
                  <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">Menyediakan referensi terpercaya seputar sejarah, teknologi, sains, dan budaya luhur Nusantara agar generasi muda semakin mengenali bangsanya.</p>
                </div>
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                  <div class="text-2xl">⚡</div>
                  <h4 class="font-bold text-base text-zinc-900 dark:text-white">Penyebar Optimisme</h4>
                  <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">Mengangkat karya anak bangsa, UMKM, peneliti, dan penggerak sosial yang berkontribusi nyata bagi kemakmuran Indonesia.</p>
                </div>
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-2.5">
                  <div class="text-2xl">📸</div>
                  <h4 class="font-bold text-base text-zinc-900 dark:text-white">Kreativitas Visual</h4>
                  <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">Menghadirkan sajian berita modern melalui GNN TV, Ceritera Story 9:16, dan studio jurnalisme warga yang inklusif.</p>
                </div>
              </div>

            </div>

          <?php else: ?>
            <!-- ================= KONTAK IKLAN & KERJASAMA ================= -->
            <div class="space-y-8">
              
              <div class="p-5 sm:p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-800/60 border-l-4 border-red-600 text-zinc-800 dark:text-zinc-200 text-sm leading-relaxed space-y-3">
                <p class="font-bold text-zinc-900 dark:text-white text-base">Terhubung dengan Good News Nusantara</p>
                <p>
                  Punya informasi kabar baik, siaran pers, undangan liputan, atau ingin berkolaborasi dalam kampanye positif dan pemasangan iklan advertorial? Tim Good News Nusantara siap menyambut Anda.
                </p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                  <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 flex items-center justify-center font-bold text-lg">✉️</div>
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white">Redaksi &amp; Siaran Pers</h3>
                  <p class="text-xs text-zinc-600 dark:text-zinc-400">Kirim naskah siaran pers, agenda kegiatan, atau hak koreksi ke:</p>
                  <a href="mailto:redaksi@gnn.web.id" class="inline-block text-sm font-bold text-red-600 hover:underline">redaksi@gnn.web.id</a>
                </div>

                <div class="p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-3">
                  <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 text-red-600 flex items-center justify-center font-bold text-lg">💼</div>
                  <h3 class="font-bold text-base text-zinc-900 dark:text-white">Kerjasama &amp; Pemasangan Iklan</h3>
                  <p class="text-xs text-zinc-600 dark:text-zinc-400">Untuk paket advertorial, sponsorship video GNN TV, dan branding:</p>
                  <a href="mailto:admin@gnn.id" class="inline-block text-sm font-bold text-red-600 hover:underline">admin@gnn.id</a>
                </div>

              </div>

            </div>

          <?php endif; ?>

        </div>

      </div>

    <?php else: ?>
      <!-- ========================================== -->
      <!-- HOMEPAGE & CATEGORY FEED VIEW -->
      <!-- ========================================== -->

      <?php if (empty($categoryParam) && empty($queryParam) && $featuredArticle): ?>
        
        <!-- ============================================================== -->
        <!-- FOTO 1: TAMPILAN PERTAMA UTAMA WEB (HEADLINE & TOP 4 NEWS) -->
        <!-- ============================================================== -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 pb-10 border-b border-zinc-200 dark:border-zinc-800 mb-10">
          
          <!-- Left Column (7-8 cols): Headline with Category, Title, Excerpt, Author, and Cover Photo Below -->
          <div class="lg:col-span-7 xl:col-span-8 flex flex-col justify-between">
            <div>
              <!-- Red Uppercase Category Tag (Matching Foto 1) -->
              <span class="text-[#CC0000] font-black text-xs sm:text-sm tracking-wider uppercase block mb-2">
                <?= htmlspecialchars($featuredArticle['sub_category'] ?: $featuredArticle['category']) ?>
              </span>
              
              <!-- Large Bold Headline Title -->
              <h1 class="font-sans font-black text-2xl sm:text-3xl lg:text-4xl text-zinc-900 dark:text-zinc-100 hover:text-[#CC0000] transition leading-[1.2] tracking-tight mb-3">
                <a href="/<?= slugify($featuredArticle['title']) ?>">
                  <?= htmlspecialchars($featuredArticle['title']) ?>
                </a>
              </h1>

              <!-- Excerpt / Ringkasan Berita -->
              <p class="text-zinc-600 dark:text-zinc-400 text-sm sm:text-base leading-relaxed line-clamp-3 mb-4">
                <?= htmlspecialchars(mb_substr(strip_tags($featuredArticle['content']), 0, 260)) ?>...
              </p>

              <!-- Author & Date Line (Matching Foto 1) -->
              <div class="text-[11px] font-bold text-zinc-400 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                <span><?= htmlspecialchars($featuredArticle['author'] ?: 'Redaksi GNN') ?></span>
                <span>•</span>
                <span><?= htmlspecialchars($featuredArticle['date'] ?: date('d M Y', strtotime($featuredArticle['created_at']))) ?></span>
              </div>
            </div>

            <!-- Large Cover Image (BELOW title, excerpt, and author line) -->
            <a href="/<?= slugify($featuredArticle['title']) ?>" class="block rounded-2xl overflow-hidden aspect-16/9 bg-zinc-100 dark:bg-zinc-800 group shadow-xs">
              <img src="<?= htmlspecialchars(getSafeArticleImage($featuredArticle['image'] ?? '', $featuredArticle['category'] ?? '')) ?>" alt="<?= htmlspecialchars($featuredArticle['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="w-full h-full object-cover group-hover:scale-103 transition duration-500">
            </a>
          </div>

          <!-- Right Column (4-5 cols): 4 Top News List (Matching Foto 1) -->
          <div class="lg:col-span-5 xl:col-span-4 flex flex-col justify-between">
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
              <?php foreach ($topNewsList as $item): ?>
                <div class="flex items-start gap-3.5 sm:gap-4 py-3 sm:py-3.5 first:pt-0 last:pb-0 group">
                  <a href="/<?= slugify($item['title']) ?>" class="w-28 sm:w-32 aspect-16/9 sm:aspect-4/3 rounded-xl overflow-hidden shrink-0 bg-zinc-100 dark:bg-zinc-800 block shadow-2xs">
                    <img src="<?= htmlspecialchars(getSafeArticleImage($item['image'] ?? '', $item['category'] ?? '')) ?>" alt="<?= htmlspecialchars($item['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                  </a>
                  <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] transition leading-snug line-clamp-3">
                      <a href="/<?= slugify($item['title']) ?>">
                        <?= htmlspecialchars($item['title']) ?>
                      </a>
                    </h3>
                    <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mt-1.5">
                      <?= htmlspecialchars($item['author'] ?: 'Redaksi GNN') ?> • <?= htmlspecialchars($item['date'] ?: date('d M Y', strtotime($item['created_at']))) ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        </section>

        <!-- ============================================================== -->
        <!-- FOTO 2: GNN TV / TOPIK KHUSUS (MATCHING FOTO 2) -->
        <!-- ============================================================== -->
        <section class="pb-10 border-b border-zinc-200 dark:border-zinc-800 mb-10 relative">
          <!-- Header: GNN TV + Red Indeks Link -->
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-sans font-black text-xl sm:text-2xl text-zinc-900 dark:text-zinc-100 tracking-tight">
              GNN TV
            </h2>
            <a href="?view=tv" class="text-[#CC0000] font-bold text-xs sm:text-sm hover:underline">
              Indeks
            </a>
          </div>

          <!-- Carousel Container with Navigation Buttons -->
          <div class="relative group/carousel">
            <!-- Left Arrow Button -->
            <button type="button" onclick="scrollGnnTv(-1)" class="absolute -left-3 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 shadow-md border border-zinc-200 dark:border-zinc-700 flex items-center justify-center hover:bg-zinc-50 dark:hover:bg-zinc-700 transition cursor-pointer">
              <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <!-- Horizontal Scroll Cards -->
            <div id="gnnTvScroll" class="flex gap-4 sm:gap-5 overflow-x-auto scroll-smooth py-1 px-1 scrollbar-none" style="scrollbar-width: none; -ms-overflow-style: none;">
              <?php 
              $tvItems = !empty($videos) ? $videos : [
                  ['id' => 'v1', 'youtube_id' => '50F_bFuhw7Y', 'title' => 'DI MOROWALI, KAWASAN INDUSTRI HIJAU TUMBUH', 'description' => 'untuk Masa Depan Generasi Muda Indonesia'],
                  ['id' => 'v2', 'youtube_id' => 'w80-jEaF5o0', 'title' => 'HYROX, OLAHRAGA BARU YANG KINI MENJAMAH INDONESIA', 'description' => 'Inovasi kebugaran dan prestasi atletik nasional'],
                  ['id' => 'v3', 'youtube_id' => 'd5w9Tf2u47g', 'title' => 'WASTRA NUSANTARA: Keindahan Budaya & Tradisi', 'description' => 'Kekayaan budaya dan ekonomi kreatif daerah']
              ];
              foreach ($tvItems as $v):
                  $yId = $v['youtube_id'] ?? '';
                  $thumb = !empty($v['image_url']) ? $v['image_url'] : ($yId ? "https://img.youtube.com/vi/$yId/hqdefault.jpg" : 'https://images.unsplash.com/photo-1578328819058-b69f3a3b0f6b?auto=format&fit=crop&w=800&q=80');
              ?>
                <div onclick="openVideoModal('<?= htmlspecialchars($yId) ?>', '<?= htmlspecialchars(addslashes($v['title'])) ?>')" class="w-[280px] sm:w-[350px] aspect-16/9 rounded-2xl overflow-hidden relative group shrink-0 shadow-xs cursor-pointer select-none bg-black">
                  <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($v['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500 opacity-85">
                  <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/45 to-transparent"></div>
                  
                  <!-- Top Left Badge -->
                  <div class="absolute top-3 left-3 flex items-center gap-1.5 z-10">
                    <span class="bg-[#CC0000] text-white font-black text-[9px] px-2 py-0.5 rounded uppercase tracking-wider shadow-xs">GNN TV</span>
                  </div>

                  <!-- Yellow Bold Headline Text (Matching Foto 2) -->
                  <div class="absolute bottom-0 inset-x-0 p-4 z-10">
                    <h3 class="font-black text-xs sm:text-sm text-yellow-300 uppercase tracking-tight leading-snug line-clamp-2 drop-shadow-sm">
                      <?= htmlspecialchars($v['title']) ?>
                    </h3>
                    <?php if (!empty($v['description'])): ?>
                      <p class="text-[11px] text-zinc-300 line-clamp-1 mt-1 font-medium"><?= htmlspecialchars($v['description']) ?></p>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Right Arrow Button -->
            <button type="button" onclick="scrollGnnTv(1)" class="absolute -right-3 top-1/2 -translate-y-1/2 z-20 w-9 h-9 rounded-full bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 shadow-md border border-zinc-200 dark:border-zinc-700 flex items-center justify-center hover:bg-zinc-50 dark:hover:bg-zinc-700 transition cursor-pointer">
              <svg class="w-4 h-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </section>

        <!-- ============================================================== -->
        <!-- FOTO 3 & 4: KABAR BERITA TERKINI & KILAS (LEFT) + SEJARAH (RIGHT) -->
        <!-- ============================================================== -->
        <section class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 pb-12">
          
          <!-- LEFT COLUMN (7-8 cols): Kabar Berita Terkini + Kilas -->
          <div class="lg:col-span-7 xl:col-span-8 space-y-10">
            
            <!-- FOTO 3: KABAR BERITA TERKINI -->
            <div>
              <div class="flex items-center justify-between mb-5 pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <h2 class="font-sans font-black text-xl sm:text-2xl text-zinc-900 dark:text-zinc-100 tracking-tight">
                  Kabar Berita Terkini
                </h2>
                <span class="text-xs font-bold text-zinc-400 uppercase tracking-wider">Terbaru</span>
              </div>

              <div class="space-y-5">
                <?php foreach ($kabarTerkiniList as $art): ?>
                  <div class="flex items-start gap-4 sm:gap-5 pb-5 border-b border-zinc-100 dark:border-zinc-800 last:border-b-0 group">
                    <a href="/<?= slugify($art['title']) ?>" class="w-32 sm:w-44 aspect-16/9 sm:aspect-4/3 rounded-xl overflow-hidden shrink-0 bg-zinc-100 dark:bg-zinc-800 block shadow-2xs">
                      <img src="<?= htmlspecialchars(getSafeArticleImage($art['image'] ?? '', $art['category'] ?? '')) ?>" alt="<?= htmlspecialchars($art['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                    </a>
                    <div class="flex-1 min-w-0">
                      <!-- Red Category Tag (Matching Foto 3) -->
                      <span class="text-[#CC0000] font-black text-[10px] sm:text-[11px] uppercase tracking-wider block mb-1">
                        <?= htmlspecialchars($art['sub_category'] ?: $art['category']) ?>
                      </span>
                      <h3 class="font-bold text-sm sm:text-base text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] transition leading-snug line-clamp-2 sm:line-clamp-3 mb-1.5">
                        <a href="/<?= slugify($art['title']) ?>">
                          <?= htmlspecialchars($art['title']) ?>
                        </a>
                      </h3>
                      <div class="text-[10px] sm:text-[11px] font-bold text-zinc-400 uppercase tracking-wider">
                        <?= htmlspecialchars($art['author'] ?: 'Redaksi GNN') ?> • <?= htmlspecialchars($art['date'] ?: date('d M Y', strtotime($art['created_at']))) ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- SOSIAL (TRENDING SOSIAL - SESUAI FORMAT FOTO 3) -->
            <div>
              <div class="flex items-center justify-between mb-5 pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full bg-[#CC0000] inline-block animate-pulse"></span>
                  <h2 class="font-sans font-black text-xl sm:text-2xl text-zinc-900 dark:text-zinc-100 tracking-tight">
                    Sosial
                  </h2>
                </div>
                <a href="?category=SOSIAL" class="text-xs font-bold text-zinc-400 hover:text-[#CC0000] uppercase tracking-wider transition">
                  Trending
                </a>
              </div>

              <div class="space-y-5">
                <?php if (!empty($trendingSosial)): ?>
                  <?php foreach ($trendingSosial as $art): ?>
                    <div class="flex items-start gap-4 sm:gap-5 pb-5 border-b border-zinc-100 dark:border-zinc-800 last:border-b-0 group">
                      <a href="/<?= slugify($art['title']) ?>" class="w-32 sm:w-44 aspect-16/9 sm:aspect-4/3 rounded-xl overflow-hidden shrink-0 bg-zinc-100 dark:bg-zinc-800 block shadow-2xs">
                        <img src="<?= htmlspecialchars(getSafeArticleImage($art['image'] ?? '', $art['category'] ?? '')) ?>" alt="<?= htmlspecialchars($art['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                      </a>
                      <div class="flex-1 min-w-0">
                        <span class="text-[#CC0000] font-black text-[10px] sm:text-[11px] uppercase tracking-wider block mb-1">
                          <?= htmlspecialchars($art['sub_category'] ?: $art['category']) ?>
                        </span>
                        <h3 class="font-bold text-sm sm:text-base text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] transition leading-snug line-clamp-2 sm:line-clamp-3 mb-1.5">
                          <a href="/<?= slugify($art['title']) ?>">
                            <?= htmlspecialchars($art['title']) ?>
                          </a>
                        </h3>
                        <div class="text-[10px] sm:text-[11px] font-bold text-zinc-400 uppercase tracking-wider">
                          <?= htmlspecialchars($art['author'] ?: 'Redaksi GNN') ?> • <?= htmlspecialchars($art['date'] ?: date('d M Y', strtotime($art['created_at']))) ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="text-xs text-zinc-400 py-4">Belum ada berita Sosial.</p>
                <?php endif; ?>
              </div>
            </div>

            <!-- FOTO 4: OLAHRAGA (MENAMPILKAN DATA DARI SUB KATEGORI OLAHRAGA) -->
            <div class="pt-2">
              <div class="flex items-center justify-between mb-4 pb-2 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                  <h2 class="font-sans font-black text-xl sm:text-2xl text-zinc-900 dark:text-zinc-100 tracking-tight">
                    Olahraga
                  </h2>
                </div>
                <a href="?category=LIFESTYLE&sub=Olahraga" class="text-[#CC0000] font-bold text-xs sm:text-sm hover:underline">
                  Indeks
                </a>
              </div>

              <!-- Kilas Carousel / Cards (Matching Foto 4) -->
              <div class="relative group/kilas">
                <!-- Left Arrow -->
                <button type="button" onclick="scrollKilas(-1)" class="absolute -left-3 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 shadow-md border border-zinc-200 dark:border-zinc-700 flex items-center justify-center hover:bg-zinc-50 transition cursor-pointer">
                  <svg class="w-3.5 h-3.5 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>

                <div id="kilasScroll" class="flex gap-4 sm:gap-5 overflow-x-auto scroll-smooth py-1 px-1 scrollbar-none" style="scrollbar-width: none; -ms-overflow-style: none;">
                  <?php foreach ($kilasSportList as $sp): ?>
                    <div class="w-[260px] sm:w-[310px] rounded-2xl border border-zinc-200 dark:border-zinc-800 overflow-hidden bg-white dark:bg-zinc-900 hover:border-[#CC0000] transition group shrink-0 flex flex-col justify-between shadow-xs">
                      <a href="/<?= slugify($sp['title']) ?>" class="block">
                        <div class="aspect-16/9 w-full overflow-hidden bg-zinc-100 dark:bg-zinc-800 relative">
                          <img src="<?= htmlspecialchars(getSafeArticleImage($sp['image'] ?? '', $sp['category'] ?? '')) ?>" alt="<?= htmlspecialchars($sp['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                          <span class="absolute top-2 left-2 bg-[#CC0000]/90 text-white font-black text-[9px] px-2 py-0.5 rounded uppercase tracking-wider"><?= htmlspecialchars(strtoupper($sp['sub_category'] ?: 'OLAHRAGA')) ?></span>
                        </div>
                        <div class="p-4">
                          <!-- Sub Category Badge -->
                          <span class="text-[#CC0000] font-black text-[10px] uppercase tracking-wider block mb-1"><?= htmlspecialchars(strtoupper($sp['sub_category'] ?: 'OLAHRAGA')) ?></span>
                          <!-- Red Title (Matching Foto 4) -->
                          <h3 class="font-bold text-xs sm:text-sm text-[#CC0000] group-hover:text-red-700 leading-snug line-clamp-3">
                            <?= htmlspecialchars($sp['title']) ?>
                          </h3>
                        </div>
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>

                <!-- Right Arrow -->
                <button type="button" onclick="scrollKilas(1)" class="absolute -right-3 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 shadow-md border border-zinc-200 dark:border-zinc-700 flex items-center justify-center hover:bg-zinc-50 transition cursor-pointer">
                  <svg class="w-3.5 h-3.5 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
              </div>
            </div>

          </div>

          <!-- RIGHT COLUMN (4-5 cols): 🔴 SEJARAH + 🔴 SAINTEK + 🔴 LIFESTYLE (Filling the right space) -->
          <div class="lg:col-span-5 xl:col-span-4 space-y-6">
            
            <!-- WIDGET 1: 🔴 SEJARAH -->
            <div class="bg-zinc-50/70 dark:bg-zinc-900/50 p-5 sm:p-6 rounded-2xl border border-zinc-200 dark:border-zinc-800">
              <div class="flex items-center justify-between mb-4 pb-2 border-b border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full bg-[#CC0000] inline-block animate-pulse"></span>
                  <h3 class="font-sans font-black text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 uppercase tracking-widest">
                    SEJARAH
                  </h3>
                </div>
                <a href="?category=SEJARAH" class="text-xs font-bold text-zinc-400 hover:text-[#CC0000] uppercase tracking-wider transition">
                  Indeks
                </a>
              </div>

              <!-- Top Featured Sejarah Card with Cover Image -->
              <?php if (!empty($sejarahArticles)): ?>
                <?php $topSejarah = $sejarahArticles[0]; ?>
                <div class="mb-5 pb-5 border-b border-zinc-200 dark:border-zinc-800 group">
                  <a href="/<?= slugify($topSejarah['title']) ?>" class="block">
                    <div class="aspect-16/9 rounded-xl overflow-hidden bg-zinc-100 dark:bg-zinc-800 mb-3 shadow-2xs">
                      <img src="<?= htmlspecialchars(getSafeArticleImage($topSejarah['image'] ?? '', $topSejarah['category'] ?? '')) ?>" alt="<?= htmlspecialchars($topSejarah['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                    </div>
                    <h4 class="font-bold text-sm sm:text-base text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] transition leading-snug line-clamp-3 mb-1.5">
                      <?= htmlspecialchars($topSejarah['title']) ?>
                    </h4>
                    <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">
                      <?= htmlspecialchars($topSejarah['author'] ?: 'Redaksi GNN') ?> • <?= htmlspecialchars($topSejarah['date'] ?: date('d M Y', strtotime($topSejarah['created_at']))) ?>
                    </div>
                  </a>
                </div>

                <!-- Followed by Headline Text Links -->
                <div class="space-y-4">
                  <?php foreach (array_slice($sejarahArticles, 1, 4) as $sArt): ?>
                    <div class="pb-3.5 border-b border-zinc-200/60 dark:border-zinc-800/60 last:border-b-0 group">
                      <h4 class="font-bold text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] transition leading-snug line-clamp-3 mb-1">
                        <a href="/<?= slugify($sArt['title']) ?>">
                          <?= htmlspecialchars($sArt['title']) ?>
                        </a>
                      </h4>
                      <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">
                        <?= htmlspecialchars($sArt['author'] ?: 'Redaksi GNN') ?> • <?= htmlspecialchars($sArt['date'] ?: date('d M Y', strtotime($sArt['created_at']))) ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- WIDGET 2: 🔴 SAINTEK (TRENDING) -->
            <div class="bg-zinc-50/70 dark:bg-zinc-900/50 p-5 sm:p-6 rounded-2xl border border-zinc-200 dark:border-zinc-800">
              <div class="flex items-center justify-between mb-4 pb-2 border-b border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full bg-[#CC0000] inline-block animate-pulse"></span>
                  <h3 class="font-sans font-black text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 uppercase tracking-widest">
                    SAINTEK
                  </h3>
                </div>
                <a href="?category=SAINTEK" class="text-xs font-bold text-zinc-400 hover:text-[#CC0000] uppercase tracking-wider transition">
                  Trending
                </a>
              </div>

              <div class="space-y-4">
                <?php if (!empty($trendingSaintek)): ?>
                  <?php foreach ($trendingSaintek as $art): ?>
                    <div class="flex items-start gap-3.5 pb-4 border-b border-zinc-200/60 dark:border-zinc-800/60 last:border-b-0 last:pb-0 group">
                      <a href="/<?= slugify($art['title']) ?>" class="w-24 sm:w-28 aspect-16/9 rounded-lg overflow-hidden shrink-0 bg-zinc-100 dark:bg-zinc-800 block shadow-2xs">
                        <img src="<?= htmlspecialchars(getSafeArticleImage($art['image'] ?? '', $art['category'] ?? '')) ?>" alt="<?= htmlspecialchars($art['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                      </a>
                      <div class="flex-1 min-w-0">
                        <span class="text-[#CC0000] font-black text-[10px] uppercase tracking-wider block mb-1">
                          <?= htmlspecialchars($art['sub_category'] ?: $art['category']) ?>
                        </span>
                        <h4 class="font-bold text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] transition leading-snug line-clamp-2 mb-1">
                          <a href="/<?= slugify($art['title']) ?>">
                            <?= htmlspecialchars($art['title']) ?>
                          </a>
                        </h4>
                        <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider truncate">
                          <?= htmlspecialchars($art['author'] ?: 'Redaksi GNN') ?> • <?= htmlspecialchars($art['date'] ?: date('d M Y', strtotime($art['created_at']))) ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="text-xs text-zinc-400 py-2">Belum ada berita Saintek.</p>
                <?php endif; ?>
              </div>
            </div>

            <!-- WIDGET 3: 🔴 LIFESTYLE (TRENDING) -->
            <div class="bg-zinc-50/70 dark:bg-zinc-900/50 p-5 sm:p-6 rounded-2xl border border-zinc-200 dark:border-zinc-800">
              <div class="flex items-center justify-between mb-4 pb-2 border-b border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full bg-[#CC0000] inline-block animate-pulse"></span>
                  <h3 class="font-sans font-black text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 uppercase tracking-widest">
                    LIFESTYLE
                  </h3>
                </div>
                <a href="?category=LIFESTYLE" class="text-xs font-bold text-zinc-400 hover:text-[#CC0000] uppercase tracking-wider transition">
                  Trending
                </a>
              </div>

              <div class="space-y-4">
                <?php if (!empty($trendingLifestyle)): ?>
                  <?php foreach ($trendingLifestyle as $art): ?>
                    <div class="flex items-start gap-3.5 pb-4 border-b border-zinc-200/60 dark:border-zinc-800/60 last:border-b-0 last:pb-0 group">
                      <a href="/<?= slugify($art['title']) ?>" class="w-24 sm:w-28 aspect-16/9 rounded-lg overflow-hidden shrink-0 bg-zinc-100 dark:bg-zinc-800 block shadow-2xs">
                        <img src="<?= htmlspecialchars(getSafeArticleImage($art['image'] ?? '', $art['category'] ?? '')) ?>" alt="<?= htmlspecialchars($art['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                      </a>
                      <div class="flex-1 min-w-0">
                        <span class="text-[#CC0000] font-black text-[10px] uppercase tracking-wider block mb-1">
                          <?= htmlspecialchars($art['sub_category'] ?: $art['category']) ?>
                        </span>
                        <h4 class="font-bold text-xs sm:text-sm text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] transition leading-snug line-clamp-2 mb-1">
                          <a href="/<?= slugify($art['title']) ?>">
                            <?= htmlspecialchars($art['title']) ?>
                          </a>
                        </h4>
                        <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider truncate">
                          <?= htmlspecialchars($art['author'] ?: 'Redaksi GNN') ?> • <?= htmlspecialchars($art['date'] ?: date('d M Y', strtotime($art['created_at']))) ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="text-xs text-zinc-400 py-2">Belum ada berita Lifestyle.</p>
                <?php endif; ?>
              </div>
            </div>

          </div>

        </section>

      <?php else: ?>

        <!-- ========================================== -->
        <!-- CATEGORY & SEARCH RESULTS FEED VIEW -->
        <!-- ========================================== -->
        <div class="border-b-2 border-zinc-900 pb-2 mb-6 flex items-center justify-between">
          <h2 class="font-serif font-black text-xl sm:text-2xl text-zinc-900 dark:text-zinc-100">
            <?php if ($categoryParam): ?>
              KABAR BAIK: <?= htmlspecialchars($categoryParam) ?>
            <?php elseif ($queryParam): ?>
              HASIL PENCARIAN: "<?= htmlspecialchars($queryParam) ?>" (<?= count($filteredArticles) ?> berita)
            <?php else: ?>
              KABAR TERKINI NUSANTARA
            <?php endif; ?>
          </h2>
          <span class="text-xs font-bold text-zinc-500 uppercase tracking-wider">
            <?= count($filteredArticles) ?> Berita
          </span>
        </div>

        <?php if (empty($filteredArticles)): ?>
          <div class="bg-white dark:bg-zinc-900 p-8 rounded-xl border border-zinc-200 dark:border-zinc-800 text-center text-zinc-500 text-sm">
            Tidak ditemukan berita yang cocok. Silakan pilih kategori lain atau gunakan kata kunci pencarian yang berbeda.
          </div>
        <?php else: ?>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            <?php foreach ($filteredArticles as $art): ?>
              <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between group hover:shadow-md transition h-full">
                <a href="/<?= slugify($art['title']) ?>" class="block">
                  <div class="card-cover" style="aspect-ratio: 16 / 9;">
                    <img src="<?= htmlspecialchars(getSafeArticleImage($art['image'] ?? '', $art['category'] ?? '')) ?>" alt="<?= htmlspecialchars($art['title']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';" class="group-hover:scale-105 transition duration-500">
                    <span class="absolute top-3 left-3 bg-[#CC0000] text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider shadow-xs">
                      <?= htmlspecialchars($art['sub_category'] ?: $art['category']) ?>
                    </span>
                  </div>
                  <div class="p-4 sm:p-5 flex-1">
                    <h3 class="font-serif font-bold text-base sm:text-lg text-zinc-900 dark:text-zinc-100 group-hover:text-[#CC0000] transition line-clamp-2 leading-snug mb-2">
                      <?= htmlspecialchars($art['title']) ?>
                    </h3>
                    <p class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 line-clamp-2 leading-relaxed">
                      <?= htmlspecialchars(strip_tags($art['content'])) ?>
                    </p>
                  </div>
                </a>
                <div class="p-4 sm:p-5 pt-0 text-[11px] text-zinc-400 flex items-center justify-between border-t border-zinc-100 dark:border-zinc-800 mt-auto">
                  <span class="font-medium text-zinc-500 dark:text-zinc-400"><?= htmlspecialchars($art['author'] ?: 'Redaksi GNN') ?></span>
                  <div class="flex items-center gap-3">
                    <span><?= htmlspecialchars($art['date'] ?: date('d M Y', strtotime($art['created_at']))) ?></span>
                    <a href="/<?= slugify($art['title']) ?>/render" class="text-[#CC0000] hover:underline font-bold" title="Render desain gambar story">🎨 Render</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>

    <?php endif; ?>

  </main>

  <!-- ============================================================== -->
  <!-- FOOTER (EXACTLY MATCHING USER SCREENSHOT) -->
  <!-- ============================================================== -->
  <footer class="bg-[#0a0a0a] text-white border-t border-zinc-800 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12 items-start mb-10">
        
        <!-- Left Column (5 cols): Logo & Brand Statement -->
        <div class="md:col-span-5 space-y-3.5">
          <div class="flex items-center gap-3.5">
            <img src="/icon/logow.png" alt="Good News Nusantara" class="h-11 sm:h-13 w-auto object-contain" onerror="this.onerror=null; this.src='/logow.png';">
            <span class="font-extrabold text-white text-base sm:text-lg tracking-tight">
              GOOD NEWS NUSANTARA
            </span>
          </div>
          <p class="text-zinc-400 text-xs leading-relaxed max-w-sm">
            Portal berita positif &amp; kabar baik seputar Indonesia. Membangun optimisme, kebanggaan, dan wawasan nusantara secara independen.
          </p>
        </div>

        <!-- Right Columns (7 cols split into 3 clean groups) -->
        <div class="md:col-span-7 grid grid-cols-2 sm:grid-cols-3 gap-6 sm:gap-8">
          
          <!-- Group 1: Kategori -->
          <div class="space-y-2.5">
            <h4 class="text-white font-bold text-xs uppercase tracking-wider">KATEGORI</h4>
            <ul class="space-y-1.5 text-xs text-zinc-400">
              <li><a href="?category=EKONOMI" class="hover:text-white transition">Ekonomi &amp; Bisnis</a></li>
              <li><a href="?category=SAINTEK" class="hover:text-white transition">Saintek &amp; Teknologi</a></li>
              <li><a href="?category=POLITIK" class="hover:text-white transition">Politik &amp; Kebijakan</a></li>
              <li><a href="?category=SOSIAL" class="hover:text-white transition">Sosial &amp; Budaya</a></li>
              <li><a href="?category=LIFESTYLE" class="hover:text-white transition">Lifestyle &amp; Olahraga</a></li>
            </ul>
          </div>

          <!-- Group 2: Layanan -->
          <div class="space-y-2.5">
            <h4 class="text-white font-bold text-xs uppercase tracking-wider">LAYANAN</h4>
            <ul class="space-y-1.5 text-xs text-zinc-400">
              <li><a href="?view=render" class="hover:text-white transition">Story 9:16 Creator / Render Foto</a></li>
              <li><a href="/studio/" class="hover:text-white transition">Studio Redaksi</a></li>
              <li><a href="?page=pedoman" class="hover:text-white transition">Pedoman Media Siber</a></li>
              <li><a href="?page=tentang" class="hover:text-white transition">Tentang Kami</a></li>
            </ul>
          </div>

          <!-- Group 3: Informasi -->
          <div class="space-y-2.5">
            <h4 class="text-white font-bold text-xs uppercase tracking-wider">INFORMASI</h4>
            <ul class="space-y-1.5 text-xs text-zinc-400">
              <li><a href="?page=privasi" class="hover:text-white transition">Kebijakan Privasi</a></li>
              <li><a href="?page=aturan" class="hover:text-white transition">Ketentuan Layanan</a></li>
              <li><a href="?page=redaksi" class="hover:text-white transition">Susunan Dewan Redaksi</a></li>
              <li><a href="?page=kontak" class="hover:text-white transition">Kontak &amp; Kerjasama</a></li>
              <li><a href="/sitemap.xml" target="_blank" class="hover:text-white transition">Sitemap</a></li>
            </ul>
          </div>

        </div>

      </div>

      <!-- Bottom Bar -->
      <div class="pt-6 border-t border-zinc-800/60 flex flex-col sm:flex-row items-center justify-between gap-3 text-zinc-500 text-[11px]">
        <p>&copy; <?= date('Y') ?> GOOD NEWS NUSANTARA (GNN). Hak Cipta Dilindungi Undang-Undang.</p>
        <p class="font-semibold text-zinc-400">Jadi Tahu Indonesia</p>
      </div>
    </div>
  </footer>

  <!-- Video Player Modal for GNN TV -->
  <div id="modalGnnTvPlayer" class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-zinc-900 rounded-3xl max-w-3xl w-full overflow-hidden shadow-2xl border border-zinc-800">
      <div class="flex items-center justify-between p-4 border-b border-zinc-800">
        <h3 id="modalGnnTvTitle" class="font-bold text-sm text-white truncate">Video GNN TV</h3>
        <button type="button" onclick="closeVideoModal()" class="text-zinc-400 hover:text-white text-xl px-2 cursor-pointer">&times;</button>
      </div>
      <div class="aspect-video w-full bg-black">
        <iframe id="modalGnnTvIframe" class="w-full h-full" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
      </div>
    </div>
  </div>

  <script>
    function scrollGnnTv(dir) {
      const el = document.getElementById('gnnTvScroll');
      if (el) el.scrollBy({ left: dir * 360, behavior: 'smooth' });
    }

    function scrollKilas(dir) {
      const el = document.getElementById('kilasScroll');
      if (el) el.scrollBy({ left: dir * 320, behavior: 'smooth' });
    }

    function openVideoModal(yId, title) {
      if (!yId) return;
      const modal = document.getElementById('modalGnnTvPlayer');
      const iframe = document.getElementById('modalGnnTvIframe');
      const titleEl = document.getElementById('modalGnnTvTitle');
      if (modal && iframe) {
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(yId) + '?autoplay=1';
        if (titleEl) titleEl.textContent = title || 'Video GNN TV';
        modal.classList.remove('hidden');
      }
    }

    function closeVideoModal() {
      const modal = document.getElementById('modalGnnTvPlayer');
      const iframe = document.getElementById('modalGnnTvIframe');
      if (modal && iframe) {
        iframe.src = '';
        modal.classList.add('hidden');
      }
    }

    function toggleDarkMode() {
      document.documentElement.classList.toggle('dark');
      localStorage.setItem('gnn_theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    }

    // Initialize Theme
    if (localStorage.getItem('gnn_theme') === 'dark' || (!localStorage.getItem('gnn_theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
    }
  </script>

</body>
</html>
