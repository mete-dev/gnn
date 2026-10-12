<?php
// Dynamic XML Sitemap for Good News Nusantara | Jadi Tahu Indonesia
require_once __DIR__ . '/api/config.php';

header("Content-Type: application/xml; charset=UTF-8");
header("X-Robots-Tag: noindex");

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "https://";
$host = $_SERVER['HTTP_HOST'] ?? 'gnn.web.id';
$baseUrl = $protocol . $host;

// Database articles
$articles = [];
try {
    $db = getDBConnection();
    $stmt = $db->query("SELECT id, title, category, status, updated_at, created_at FROM articles ORDER BY created_at DESC");
    $articles = $stmt->fetchAll();
} catch (Exception $e) {
    // Graceful fallback if query fails
    $articles = [];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <!-- Homepage -->
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/') ?></loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- Categories -->
  <?php
  $categories = ['KABAR BAIK', 'INSPIRASI', 'PARIWISATA', 'BUDAYA', 'TEKNOLOGI', 'EKONOMI', 'PENDIDIKAN', 'LINGKUNGAN'];
  foreach ($categories as $cat):
  ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/?category=' . urlencode($cat)) ?></loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  <?php endforeach; ?>

  <!-- Features -->
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/?view=tv') ?></loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/?view=gallery') ?></loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>

  <!-- Static Pages -->
  <?php
  $staticPages = ['tentang', 'redaksi', 'pedoman', 'kontak'];
  foreach ($staticPages as $page):
  ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/?page=' . $page) ?></loc>
    <lastmod><?= date('Y-m-d') ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <?php endforeach; ?>

  <!-- Dynamic Articles from Database -->
  <?php
  function sitemapSlugify($text) {
      $text = preg_replace('~[^\pL\d]+~u', '-', $text ?? '');
      $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
      $text = preg_replace('~[^-\w]+~', '', $text);
      $text = trim($text, '-');
      $text = preg_replace('~-+~', '-', $text);
      $text = strtolower($text);
      return empty($text) ? 'artikel' : $text;
  }

  foreach ($articles as $art):
    $status = strtolower($art['status'] ?? 'publish');
    if ($status === 'draft') continue;

    $dateRaw = $art['updated_at'] ?: $art['created_at'] ?: date('Y-m-d');
    $lastmod = date('c', strtotime($dateRaw));
    $artUrl = $baseUrl . '/' . sitemapSlugify($art['title']);
  ?>
  <url>
    <loc><?= htmlspecialchars($artUrl) ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>hourly</changefreq>
    <priority>0.9</priority>
  </url>
  <?php endforeach; ?>
</urlset>
