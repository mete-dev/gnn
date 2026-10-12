<?php
require_once __DIR__ . '/config.php';

$db = getDBConnection();
$data = getJsonInput();
$topic = trim($data['topic'] ?? '');
$category = strtoupper(trim($data['category'] ?? 'NASIONAL'));

if (!$topic) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Topik berita harus diisi."]);
    exit();
}

$simulatedTitle = ucfirst($topic) . ": Gebrakan Baru yang Mengubah Peta Industri Nasional";
$simulatedContent = "Langkah besar kembali diambil oleh para pemangku kepentingan menyusul dinamika pesat terkait " . $topic . ". Berdasarkan laporan investigasi mendalam, perkembangan ini menandai babak baru dalam transformasi sektor strategis tanah air.\n\nSejumlah pakar dan pengamat kebijakan publik menilai terobosan ini sebagai sinyal kuat bagi percepatan modernisasi nasional. \"Kita tidak lagi sekadar menjadi pasar penonton, melainkan motor penggerak utama perubahan di kawasan regional,\" ujar salah seorang analis senior saat ditemui di Jakarta.\n\nPemerintah bersama pihak terkait kini tengah merampungkan peta jalan (roadmap) terintegrasi guna memastikan dampak positifnya dapat dirasakan langsung oleh masyarakat secara merata dan berkelanjutan.";

$newId = 'gnn-' . time();
$bullets = [
    "Gebrakan " . $topic . " picu pergeseran peta strategis nasional",
    "Pengamat nilai Indonesia kini siap jadi motor perubahan kawasan",
    "Pemerintah siapkan roadmap terintegrasi multi-sektor",
    "Simak analisis mendalam selengkapnya di Good News Nusantara"
];

$stmt = $db->prepare("INSERT INTO articles (id, category, sub_category, title, content, image, caption, breaking, featured, bullets, date, time_ago, author, editor, location, likes, views, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$dateStr = date('l, d M Y');
$stmt->execute([
    $newId,
    $category,
    'FOKUS',
    $simulatedTitle,
    $simulatedContent,
    'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1000&q=80',
    "Liputan khusus terkait dinamika " . $topic . ". (Good News Nusantara)",
    1,
    0,
    json_encode($bullets),
    $dateStr,
    'Baru saja',
    'Tim Redaksi Khusus',
    'Editor Desk Khusus',
    'Jakarta, Good News Nusantara',
    12,
    340,
    'publish'
]);

$responseArticle = [
    "id" => $newId,
    "category" => $category,
    "subCategory" => "FOKUS",
    "title" => $simulatedTitle,
    "content" => $simulatedContent,
    "image" => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1000&q=80',
    "caption" => "Liputan khusus terkait dinamika " . $topic . ". (Good News Nusantara)",
    "breaking" => true,
    "featured" => false,
    "bullets" => $bullets,
    "date" => $dateStr,
    "timeAgo" => "Baru saja",
    "author" => "Tim Redaksi Khusus",
    "editor" => "Editor Desk Khusus",
    "location" => "Jakarta, Good News Nusantara",
    "likes" => 12,
    "views" => 340,
    "comments" => []
];

echo json_encode(["success" => true, "data" => $responseArticle]);
