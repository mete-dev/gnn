<?php
/**
 * Good News Nusantara - Studio Redaksi (Full PHP Native)
 * 100% PHP Native & MySQL
 */

session_start();
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/google-indexer.php';

header("Content-Type: text/html; charset=UTF-8");

$db = getDBConnection();

if (!function_exists('slugify')) {
    function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text ?? '');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'artikel' : $text;
    }
}

// Ensure settings table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `key_name` VARCHAR(100) PRIMARY KEY,
        `value` LONGTEXT DEFAULT NULL,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

function getSetting($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return ($row && isset($row['value'])) ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($db, $key, $value) {
    try {
        $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {}
}

$googleClientId = getSetting($db, 'google_client_id', '898618797755-50lm3nni6pst91vjhv9vrvv2olo602j1.apps.googleusercontent.com');

$msg = '';
$err = '';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: /studio/");
    exit();
}

// Handle Traditional Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $identifier = strtolower(trim($_POST['identifier'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if ($identifier && $password) {
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ? OR LOWER(username) = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        $passValid = false;
        if ($user) {
            if ($user['password'] === $password || password_verify($password, $user['password'] ?? '')) {
                $passValid = true;
            } elseif (($identifier === 'admin' || $identifier === 'admin@gnn.my.id' || strtolower($user['email'] ?? '') === 'admin@gnn.my.id') && $password === 'Gnn6908') {
                $passValid = true;
            }
        } elseif (($identifier === 'admin' || $identifier === 'admin@gnn.my.id') && $password === 'Gnn6908') {
            $hashPass = password_hash('Gnn6908', PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (id, username, email, name, role, password, avatar, status, created_at) VALUES ('usr-admin-main', 'admin', 'admin@gnn.my.id', 'Administrator GNN', 'admin', ?, '', 'active', CURRENT_TIMESTAMP)")->execute([$hashPass]);
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = 'admin@gnn.my.id'");
            $stmt->execute();
            $user = $stmt->fetch();
            $passValid = true;
        }

        if ($passValid && $user) {
            $userRole = $user['role'] ?? 'sahabat';
            if (strtolower($user['email']) === 'fadhilalanshor511@gmail.com' || strtolower($user['email']) === 'admin@gnn.my.id' || strtolower($user['username']) === 'admin') {
                $userRole = 'admin';
                try {
                    $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$user['id']]);
                } catch (Exception $e) {}
            }

            $_SESSION['gnn_user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $userRole,
                'avatar' => $user['avatar'] ?? ''
            ];
            header("Location: /studio/");
            exit();
        } else {
            $err = "Username atau kata sandi tidak cocok.";
        }
    } else {
        $err = "Lengkapi form login.";
    }
}

// Handle Pendaftaran Sahabat Penulis Pers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_sahabat') {
    $name = trim($_POST['name'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $gender = trim($_POST['gender'] ?? 'Laki-laki');
    $birthDate = trim($_POST['birth_date'] ?? '');
    $country = trim($_POST['country'] ?? 'Indonesia');
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$name || !$username || !$password || !$email || !$whatsapp) {
        $err = "Semua bidang bertanda bintang (*) wajib diisi.";
    } elseif ($password !== $confirmPassword) {
        $err = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $err = "Password minimal 6 karakter.";
    } else {
        try {
            $stmtChk = $db->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = ? OR LOWER(username) = ?");
            $stmtChk->execute([$email, $username]);
            if ($stmtChk->fetchColumn() > 0) {
                $err = "Email atau Username sudah terdaftar di sistem.";
            } else {
                $newId = 'usr-s-' . time();
                $hashPass = password_hash($password, PASSWORD_DEFAULT);
                $stmtReg = $db->prepare("INSERT INTO users (id, username, email, name, role, password, avatar, status, whatsapp, gender, birth_date, country, province, city, address, created_at) VALUES (?, ?, ?, ?, 'sahabat', ?, '', 'active', ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                $stmtReg->execute([$newId, $username, $email, $name, $hashPass, $whatsapp, $gender, $birthDate, $country, $province, $city, $address]);

                $_SESSION['gnn_user'] = [
                    'id' => $newId,
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'role' => 'sahabat',
                    'avatar' => ''
                ];
                header("Location: /studio/?msg=" . urlencode("Selamat datang, " . $name . "! Pendaftaran Sahabat Penulis Pers berhasil."));
                exit();
            }
        } catch (Exception $e) {
            $err = "Pendaftaran gagal: " . $e->getMessage();
        }
    }
}

// Handle Google Login (GIS JWT or direct Google email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'google_login') {
    $credential = trim($_POST['credential'] ?? '');
    $email = '';
    $name = '';
    $avatar = '';

    if ($credential) {
        $parts = explode('.', $credential);
        if (count($parts) >= 2) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if ($payload && !empty($payload['email'])) {
                $email = strtolower(trim($payload['email']));
                $name = trim($payload['name'] ?? explode('@', $email)[0]);
                $avatar = trim($payload['picture'] ?? '');
            }
        }
    }

    // Direct Google authentication fallback
    if (!$email && !empty($_POST['google_email'])) {
        $email = strtolower(trim($_POST['google_email']));
        $name = trim($_POST['google_name'] ?? explode('@', $email)[0]);
        $avatar = trim($_POST['google_avatar'] ?? '');
    }

    if ($email) {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            $isAdminEmail = (strtolower($email) === 'fadhilalanshor511@gmail.com' || strtolower($email) === 'admin@gnn.my.id');
            $assignedRole = $isAdminEmail ? 'admin' : 'sahabat';

            if (!$user) {
                $username = explode('@', $email)[0];
                $baseUser = preg_replace('/[^a-zA-Z0-9_]/', '', $username) ?: 'redaksi';
                $uniqueUser = $baseUser;
                $chk = $db->prepare("SELECT id FROM users WHERE username = ?");
                $chk->execute([$uniqueUser]);
                if ($chk->fetch()) {
                    $uniqueUser = $baseUser . '_' . rand(10, 99);
                }

                $newId = 'usr-g-' . time();
                $genPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $stmtIns = $db->prepare("INSERT INTO users (id, username, email, name, role, password, avatar, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)");
                $stmtIns->execute([$newId, $uniqueUser, $email, $name, $assignedRole, $genPassword, $avatar]);

                $stmtGet = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmtGet->execute([$newId]);
                $user = $stmtGet->fetch();
            } else {
                if ($isAdminEmail && ($user['role'] ?? '') !== 'admin') {
                    $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$user['id']]);
                    $user['role'] = 'admin';
                }
            }

            if ($user) {
                $finalRole = $isAdminEmail ? 'admin' : ($user['role'] ?? 'sahabat');
                $_SESSION['gnn_user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $finalRole,
                    'avatar' => $user['avatar'] ?? $avatar
                ];
                header("Location: /studio/");
                exit();
            } else {
                $err = "Gagal memproses data pengguna.";
            }
        } catch (Exception $e) {
            $err = "Gagal login Google: " . $e->getMessage();
        }
    } else {
        $err = "Gagal memproses autentikasi Google. Email tidak ditemukan.";
    }
}

$currentUser = $_SESSION['gnn_user'] ?? null;
if ($currentUser && (strtolower($currentUser['email'] ?? '') === 'fadhilalanshor511@gmail.com' || strtolower($currentUser['email'] ?? '') === 'admin@gnn.my.id')) {
    $currentUser['role'] = 'admin';
    $_SESSION['gnn_user']['role'] = 'admin';
}

// Tab Routing
$tab = $_GET['tab'] ?? 'articles';
$filterStatus = $_GET['status'] ?? 'all';
$filterCategory = trim($_GET['category'] ?? 'all');
$searchQuery = trim($_GET['q'] ?? '');

// Actions requiring login
if ($currentUser) {
    // Delete Article
    if (isset($_GET['delete_art'])) {
        $delId = $_GET['delete_art'];
        $db->prepare("DELETE FROM articles WHERE id = ?")->execute([$delId]);
        header("Location: /studio/?tab=articles&msg=" . urlencode("Artikel berhasil dihapus."));
        exit();
    }

    // Delete Video
    if (isset($_GET['delete_vid'])) {
        $delId = $_GET['delete_vid'];
        $db->prepare("DELETE FROM videos WHERE id = ?")->execute([$delId]);
        header("Location: /studio/?tab=videos&msg=" . urlencode("Video berhasil dihapus."));
        exit();
    }

    // Delete Gallery
    if (isset($_GET['delete_gal'])) {
        $delId = $_GET['delete_gal'];
        $db->prepare("DELETE FROM gallery WHERE id = ?")->execute([$delId]);
        header("Location: /studio/?tab=gallery&msg=" . urlencode("Foto galeri berhasil dihapus."));
        exit();
    }

    // Save Article (Create or Edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_article') {
        $artId = trim($_POST['id'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $category = strtoupper(trim($_POST['category'] ?? 'EKONOMI'));
        $subCategory = trim($_POST['sub_category'] ?? '');
        $location = trim($_POST['location'] ?? 'Jakarta');
        $image = trim($_POST['image'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $imageCredit = trim($_POST['image_credit'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $author = trim($_POST['author'] ?? $currentUser['name']);
        $bulletsText = trim($_POST['bullets'] ?? '');
        $status = $_POST['status'] ?? 'publish';

        $bulletsArr = array_filter(array_map('trim', explode("\n", $bulletsText)));
        $bulletsJson = json_encode(array_values($bulletsArr));

        if ($title && $content) {
            // Otomatis masukkan foto cover ke Galeri Foto jika ada foto dan belum ada di galeri
            if (!empty($image)) {
                try {
                    $chkGal = $db->prepare("SELECT id FROM gallery WHERE image_url = ?");
                    $chkGal->execute([$image]);
                    if (!$chkGal->fetch()) {
                        $autoGalId = 'gal-' . time() . '-' . rand(10, 99);
                        $galTitle = $caption ?: $title;
                        $db->prepare("INSERT INTO gallery (id, title, image_url, caption, author, author_email, date) VALUES (?, ?, ?, ?, ?, ?, ?)")
                           ->execute([$autoGalId, $galTitle, $image, $caption, $author, $currentUser['email'], date('d M Y')]);
                    }
                } catch (Exception $e) {}
            }

            if ($artId) {
                // Update
                $stmt = $db->prepare("UPDATE articles SET title=?, category=?, sub_category=?, location=?, image=?, caption=?, image_credit=?, content=?, keywords=?, bullets=?, author=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([$title, $category, $subCategory, $location, $image, $caption, $imageCredit, $content, $keywords, $bulletsJson, $author, $status, $artId]);
                $msg = "Artikel berhasil diperbarui!";
            } else {
                // Insert
                $newId = 'gnn-' . time();
                $stmt = $db->prepare("INSERT INTO articles (id, title, category, sub_category, location, image, caption, image_credit, content, keywords, bullets, author, author_email, editor, likes, views, status, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Tim Redaksi GNN', 0, 0, ?, ?)");
                $stmt->execute([$newId, $title, $category, $subCategory, $location, $image, $caption, $imageCredit, $content, $keywords, $bulletsJson, $author, $currentUser['email'], $status, date('d M Y')]);
                $msg = "Artikel baru berhasil diterbitkan!";
            }

            // Real-time Instant Indexing to Google if published
            if (strtolower($status) === 'publish') {
                try {
                    $liveArticleUrl = 'https://gnn.web.id/' . slugify($title);
                    $indexRes = GoogleIndexer::notify($liveArticleUrl, 'URL_UPDATED');
                    if (!empty($indexRes['success'])) {
                        $msg .= " (Sinyal Google Indexing API berhasil dikirim!)";
                    }
                } catch (Exception $e) {}
            }

            header("Location: /studio/?tab=articles&msg=" . urlencode($msg));
            exit();
        } else {
            $err = "Judul dan isi artikel wajib diisi.";
        }
    }

    // Save Video
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_video') {
        $title = trim($_POST['title'] ?? '');
        $youtubeUrl = trim($_POST['youtube_url'] ?? '');
        $category = strtoupper(trim($_POST['category'] ?? 'PARIWISATA'));
        $description = trim($_POST['description'] ?? '');

        if ($title && $youtubeUrl) {
            $youtubeId = $youtubeUrl;
            if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $youtubeUrl, $matches)) {
                $youtubeId = $matches[1];
            }
            $newVidId = 'vid-' . time();
            $db->prepare("INSERT INTO videos (id, title, youtube_url, youtube_id, description, category, author, author_email, date, duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '04:15')")
               ->execute([$newVidId, $title, $youtubeUrl, $youtubeId, $description, $category, $currentUser['name'], $currentUser['email'], date('d M Y')]);
            header("Location: /studio/?tab=videos&msg=" . urlencode("Video berhasil ditambahkan!"));
            exit();
        } else {
            $err = "Judul dan URL YouTube harus diisi.";
        }
    }

    // Save Gallery
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_gallery') {
        $title = trim($_POST['title'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $caption = trim($_POST['caption'] ?? '');

        if ($title && $imageUrl) {
            $newGalId = 'gal-' . time();
            try {
                $db->prepare("INSERT INTO gallery (id, title, image_url, caption, author, author_email, date) VALUES (?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$newGalId, $title, $imageUrl, $caption, $currentUser['name'], $currentUser['email'], date('d M Y')]);
            } catch (Exception $e) {
                $db->prepare("INSERT INTO gallery (id, title, image_url, author, author_email, date) VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([$newGalId, $title, $imageUrl, $currentUser['name'], $currentUser['email'], date('d M Y')]);
            }
            header("Location: /studio/?tab=gallery&msg=" . urlencode("Foto galeri berhasil ditambahkan!"));
            exit();
        } else {
            $err = "Judul dan URL Gambar harus diisi.";
        }
    }

    // Save Settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
        $newClientId = trim($_POST['google_client_id'] ?? '');
        setSetting($db, 'google_client_id', $newClientId);
        $googleClientId = $newClientId;
        $msg = "Pengaturan Google Client ID berhasil disimpan!";
    }

    // Update User Role (Admin only)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user_role') {
        if (($currentUser['role'] ?? '') === 'admin') {
            $targetUserId = trim($_POST['user_id'] ?? '');
            $newRole = trim($_POST['new_role'] ?? 'sahabat');
            if (in_array($newRole, ['admin', 'reviewer', 'sahabat']) && $targetUserId) {
                $stmtRole = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmtRole->execute([$newRole, $targetUserId]);
                $msg = "Peran pengguna berhasil diubah menjadi " . strtoupper($newRole) . "!";
            }
        }
    }
}

// Data Queries
$articles = [];
$videos = [];
$gallery = [];
$users = [];
$editArticle = null;
$totalArticlesCount = 0;

if ($currentUser) {
    // Count total articles
    $totalArticlesCount = (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn();

    // Query articles with search & filter
    $artSql = "SELECT * FROM articles WHERE 1=1";
    $artParams = [];

    if ($filterStatus && $filterStatus !== 'all') {
        $artSql .= " AND status = ?";
        $artParams[] = $filterStatus;
    }

    if ($filterCategory && $filterCategory !== 'all') {
        $artSql .= " AND LOWER(category) = LOWER(?)";
        $artParams[] = $filterCategory;
    }

    if ($searchQuery) {
        $artSql .= " AND (title LIKE ? OR content LIKE ? OR author LIKE ? OR category LIKE ?)";
        $like = "%$searchQuery%";
        $artParams[] = $like;
        $artParams[] = $like;
        $artParams[] = $like;
        $artParams[] = $like;
    }

    $artSql .= " ORDER BY created_at DESC";
    $stmtArt = $db->prepare($artSql);
    $stmtArt->execute($artParams);
    $articles = $stmtArt->fetchAll();
    if ($tab === 'videos' && $searchQuery) {
        $stmtVid = $db->prepare("SELECT * FROM videos WHERE title LIKE ? OR description LIKE ? OR category LIKE ? ORDER BY created_at DESC");
        $stmtVid->execute(["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"]);
        $videos = $stmtVid->fetchAll();
    } else {
        $videos = $db->query("SELECT * FROM videos ORDER BY created_at DESC")->fetchAll();
    }

    if ($tab === 'gallery' && $searchQuery) {
        $stmtGal = $db->prepare("SELECT * FROM gallery WHERE title LIKE ? OR caption LIKE ? OR author LIKE ? ORDER BY created_at DESC");
        $stmtGal->execute(["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"]);
        $gallery = $stmtGal->fetchAll();
    } else {
        $gallery = $db->query("SELECT * FROM gallery ORDER BY created_at DESC")->fetchAll();
    }

    $users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

    if (isset($_GET['edit_art'])) {
        $stmtEdit = $db->prepare("SELECT * FROM articles WHERE id = ?");
        $stmtEdit->execute([$_GET['edit_art']]);
        $editArticle = $stmtEdit->fetch();
        if ($editArticle) {
            $tab = 'write';
            $editBullets = json_decode($editArticle['bullets'] ?: '[]', true);
            $editArticle['bullets_text'] = is_array($editBullets) ? implode("\n", $editBullets) : '';
        }
    }
}

// Kategori Resmi dari Navibar Header
$navbarCategories = [
    'Ekonomi' => ['Ekonomi', 'Bisnis', 'Investasi', 'Ketenagakerjaan'],
    'Saintek' => ['Pendidikan', 'Teknologi', 'Sains', 'Lingkungan', 'Konservasi', 'Energi Hijau'],
    'Politik' => ['Kebijakan', 'Nasional', 'Asean', 'Internasional'],
    'Hukum' => ['Hukum', 'Kriminal', 'Investigasi'],
    'Sosial' => ['Sosial', 'Budaya', 'Gastronomi', 'Seni & Sastra'],
    'Sejarah' => ['Sejarah Nusantara', 'Kerajaan', 'Purbakala', 'Peradaban', 'Cagar Budaya'],
    'Lifestyle' => ['Religi', 'Olahraga', 'Hiburan', 'Kesehatan']
];

$categoriesList = array_keys($navbarCategories);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Studio Redaksi - Good News Nusantara</title>
  <link rel="icon" type="image/png" href="/icon/favicon.png" />
  <script>
    // Filter non-critical CDN notice
    (function(){
      var origWarn = console.warn;
      console.warn = function(...args) {
        if (args[0] && typeof args[0] === 'string' && args[0].indexOf('cdn.tailwindcss.com') !== -1) return;
        origWarn.apply(console, args);
      };
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;900&family=Roboto+Serif:ital,opsz,wght@0,8..144,400..900;1,8..144,400..900&display=swap" rel="stylesheet">
  <?php if (!empty($googleClientId)): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php endif; ?>
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
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-serif { font-family: 'Roboto Serif', Georgia, serif; }
    .scrollbar-none::-webkit-scrollbar { display: none; }
    .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
  </style>
</head>
<body class="bg-[#F8F9FA] text-zinc-900 antialiased min-h-screen">

  <?php if (!$currentUser): ?>
    <!-- STUDIO LOGIN FORM WITH GOOGLE SIGN-IN -->
    <div class="min-h-screen flex items-center justify-center p-4 bg-[#F8F9FA]">
      <div class="max-w-md w-full bg-white p-8 rounded-3xl border border-zinc-200/80 shadow-xl shadow-zinc-200/50">
        
        <!-- Header Logo -->
        <div class="text-center mb-7">
          <a href="/" class="inline-block mb-3">
            <img src="/icon/logoh.png" alt="Good News Nusantara" class="h-10 w-auto mx-auto" onerror="this.onerror=null; this.src='/logoh.png';">
          </a>
          <h1 class="font-extrabold text-xl text-zinc-900 tracking-tight">STUDIO REDAKSI GNN</h1>
          <p class="text-xs text-zinc-500 mt-0.5">Kelola Penerbitan Berita, Liputan & Multimedia</p>
        </div>

        <?php if ($err): ?>
          <div class="bg-red-50 text-red-700 p-3.5 rounded-xl text-xs font-semibold mb-5 border border-red-200 flex items-center gap-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?= htmlspecialchars($err) ?></span>
          </div>
        <?php endif; ?>

        <!-- Traditional Credentials Form -->
        <form method="POST" action="" class="space-y-4 mb-5">
          <input type="hidden" name="action" value="login">
          <div>
            <label class="block text-[11px] font-bold text-zinc-600 uppercase tracking-wider mb-1.5">Username / Email</label>
            <input type="text" name="identifier" required placeholder="Masukkan username atau email anda" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none transition">
          </div>
          <div>
            <label class="block text-[11px] font-bold text-zinc-600 uppercase tracking-wider mb-1.5">Kata Sandi</label>
            <input type="password" name="password" required placeholder="Masukkan kata sandi" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 focus:ring-1 focus:ring-red-600 outline-none transition">
          </div>
          <button type="submit" class="w-full py-2.5 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-xl transition shadow-xs uppercase tracking-wider">
            Masuk ke Studio
          </button>
        </form>

        <!-- Divider -->
        <div class="relative flex py-2 items-center mb-5">
          <div class="grow border-t border-zinc-200"></div>
          <span class="shrink mx-3 text-[11px] font-medium text-zinc-400">atau masuk dengan Google</span>
          <div class="grow border-t border-zinc-200"></div>
        </div>

        <?php if (!empty($googleClientId)): ?>
          <!-- Google One-Tap Setup -->
          <div id="g_id_onload"
               data-client_id="<?= htmlspecialchars($googleClientId) ?>"
               data-callback="handleGoogleLogin"
               data-auto_prompt="false">
          </div>
        <?php endif; ?>

        <!-- Google Sign-In Container (Di Bawah) -->
        <div class="space-y-2">
          <!-- Official Google Render Button (rendered by Google GIS script) -->
          <div id="googleOfficialBtn" class="flex justify-center w-full min-h-[42px]"></div>

          <!-- Standalone Google Button (triggers OAuth2 Popup) -->
          <button type="button" id="googleSignInBtn" onclick="triggerGoogleSignIn()" class="w-full py-2.5 px-4 bg-white hover:bg-zinc-50 text-zinc-700 font-bold text-xs rounded-xl border border-zinc-300 shadow-xs hover:border-zinc-400 transition flex items-center justify-center gap-3 cursor-pointer">
            <!-- Google Multicolor Logo SVG -->
            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span>Masuk dengan Google</span>
          </button>
        </div>

        <!-- Hidden Form for Google Token Submit -->
        <form id="googleForm" method="POST" action="" style="display:none;">
          <input type="hidden" name="action" value="google_login">
          <input type="hidden" name="credential" id="googleCredential">
          <input type="hidden" name="google_email" id="googleEmail">
          <input type="hidden" name="google_name" id="googleName">
          <input type="hidden" name="google_avatar" id="googleAvatar">
        </form>

        <!-- Opsi Mendaftar Sebagai Sahabat Penulis Pers -->
        <div class="mt-5 pt-4 border-t border-zinc-100 text-center">
          <p class="text-xs text-zinc-500 mb-2">Ingin berkontribusi sebagai Sahabat Penulis Pers?</p>
          <button type="button" onclick="openSahabatRegisterModal()" class="w-full py-2.5 px-4 bg-red-50 hover:bg-red-100/80 text-[#CC0000] font-bold text-xs rounded-xl border border-red-200 transition flex items-center justify-center gap-2 cursor-pointer shadow-2xs">
            <svg class="w-4 h-4 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span>Daftar sebagai Sahabat Penulis Pers</span>
          </button>
        </div>

        <div class="mt-4 pt-3 border-t border-zinc-100 text-center">
          <a href="/" class="text-xs font-semibold text-zinc-400 hover:text-zinc-700 transition">&larr; Kembali ke Beranda GNN</a>
        </div>
      </div>
    </div>

    <!-- MODAL PENDAFTARAN SAHABAT PENULIS PERS (MULTI-STEP) -->
    <div id="sahabatRegisterModal" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 backdrop-blur-xs hidden">
      <div class="bg-white text-zinc-900 rounded-3xl max-w-xl w-full border border-zinc-200 shadow-2xl relative flex flex-col my-auto max-h-[92vh] overflow-hidden">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 bg-zinc-50/70">
          <div>
            <h3 class="text-base font-extrabold uppercase tracking-tight text-zinc-900">
              Daftar Penulis Pers
            </h3>
            <p class="text-[11px] text-zinc-500 mt-0.5">Bergabunglah bersama Gnext Indonesia dan suarakan tulisanmu ke seluruh jaringan.</p>
          </div>
          <button type="button" onclick="closeSahabatRegisterModal()" class="w-8 h-8 rounded-full bg-zinc-200/80 hover:bg-zinc-300 text-zinc-600 flex items-center justify-center text-lg font-bold transition cursor-pointer">&times;</button>
        </div>

        <!-- Stepper Indicators -->
        <div class="grid grid-cols-3 gap-2 px-6 py-3 bg-zinc-50/50 border-b border-zinc-100 text-xs">
          <div id="stepPill1" class="py-2 px-2 text-center bg-[#CC0000] text-white font-bold rounded-xl transition text-[11px]">
            1. Informasi Akun
          </div>
          <div id="stepPill2" class="py-2 px-2 text-center bg-zinc-100 text-zinc-400 font-semibold rounded-xl transition text-[11px]">
            2. Data Pribadi & Kontak
          </div>
          <div id="stepPill3" class="py-2 px-2 text-center bg-zinc-100 text-zinc-400 font-semibold rounded-xl transition text-[11px]">
            3. Domisili
          </div>
        </div>

        <!-- Form Multi-Step -->
        <form method="POST" action="" id="sahabatRegForm" class="p-6 overflow-y-auto space-y-4 max-h-[calc(92vh-160px)]">
          <input type="hidden" name="action" value="register_sahabat">

          <!-- LANGKAH 1: INFORMASI AKUN -->
          <div id="regStep1" class="space-y-4">
            <div class="flex items-center gap-2 pb-2 border-b border-zinc-100">
              <span class="w-6 h-6 rounded-full bg-red-100 text-[#CC0000] font-black text-xs flex items-center justify-center">1</span>
              <h4 class="font-bold text-xs uppercase tracking-wider text-zinc-800">Informasi Akun</h4>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Nama Lengkap *</label>
              <input type="text" name="name" id="regName" required placeholder="Masukkan nama lengkap" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Username *</label>
              <input type="text" name="username" id="regUsername" required placeholder="Pilih username unik" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Password *</label>
              <input type="password" name="password" id="regPassword" required placeholder="Buat password" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Konfirmasi Password *</label>
              <input type="password" name="confirm_password" id="regConfirmPassword" required placeholder="Ketik ulang password" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none">
            </div>
            <div class="pt-3 flex justify-end">
              <button type="button" onclick="validateAndNext(1)" class="px-5 py-2.5 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                <span>Lanjut ke Langkah 2</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </button>
            </div>
          </div>

          <!-- LANGKAH 2: DATA PRIBADI & KONTAK -->
          <div id="regStep2" class="space-y-4 hidden">
            <div class="flex items-center gap-2 pb-2 border-b border-zinc-100">
              <span class="w-6 h-6 rounded-full bg-red-100 text-[#CC0000] font-black text-xs flex items-center justify-center">2</span>
              <h4 class="font-bold text-xs uppercase tracking-wider text-zinc-800">Data Pribadi & Kontak</h4>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Email *</label>
              <input type="email" name="email" id="regEmail" required placeholder="nama@email.com" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Nomor WhatsApp *</label>
              <input type="tel" name="whatsapp" id="regWhatsapp" required placeholder="0812xxxxxx" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Jenis Kelamin *</label>
                <select name="gender" id="regGender" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none font-semibold">
                  <option value="Laki-laki">Laki-laki</option>
                  <option value="Perempuan">Perempuan</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Tanggal Lahir *</label>
                <input type="date" name="birth_date" id="regBirthDate" required class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none font-semibold">
              </div>
            </div>
            <div class="pt-3 flex items-center justify-between">
              <button type="button" onclick="goToStep(1)" class="px-4 py-2 text-xs font-bold text-zinc-500 hover:text-zinc-800 transition cursor-pointer">&larr; Kembali</button>
              <button type="button" onclick="validateAndNext(2)" class="px-5 py-2.5 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer">
                <span>Lanjut ke Langkah 3</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </button>
            </div>
          </div>

          <!-- LANGKAH 3: DOMISILI (TEMPAT TINGGAL) -->
          <div id="regStep3" class="space-y-4 hidden">
            <div class="flex items-center gap-2 pb-2 border-b border-zinc-100">
              <span class="w-6 h-6 rounded-full bg-red-100 text-[#CC0000] font-black text-xs flex items-center justify-center">3</span>
              <h4 class="font-bold text-xs uppercase tracking-wider text-zinc-800">Domisili (Tempat Tinggal)</h4>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Negara *</label>
              <select name="country" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none font-semibold">
                <option value="Indonesia">Indonesia</option>
                <option value="Malaysia">Malaysia</option>
                <option value="Singapura">Singapura</option>
                <option value="Lainnya">Lainnya</option>
              </select>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Provinsi *</label>
                <select name="province" id="regProvince" required class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none font-semibold">
                  <option value="">Pilih Provinsi</option>
                  <?php
                    $allProvinces = [
                      'DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Banten',
                      'Bali', 'Nusa Tenggara Barat', 'Nusa Tenggara Timur', 'Aceh', 'Sumatera Utara',
                      'Sumatera Barat', 'Riau', 'Kepulauan Riau', 'Jambi', 'Sumatera Selatan',
                      'Kepulauan Bangka Belitung', 'Bengkulu', 'Lampung', 'Kalimantan Barat',
                      'Kalimantan Tengah', 'Kalimantan Selatan', 'Kalimantan Timur', 'Kalimantan Utara',
                      'Sulawesi Utara', 'Gorontalo', 'Sulawesi Tengah', 'Sulawesi Barat',
                      'Sulawesi Selatan', 'Sulawesi Tenggara', 'Maluku', 'Maluku Utara',
                      'Papua', 'Papua Barat', 'Papua Tengah', 'Papua Pegunungan', 'Papua Selatan', 'Papua Barat Daya'
                    ];
                    foreach ($allProvinces as $p) {
                      echo '<option value="' . htmlspecialchars($p) . '">' . htmlspecialchars($p) . '</option>';
                    }
                  ?>
                </select>
              </div>
              <div>
                <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Kabupaten/Kota *</label>
                <input type="text" name="city" id="regCity" required placeholder="Pilih atau ketik Kab/Kota" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none">
              </div>
            </div>
            <div>
              <label class="block text-[11px] font-bold text-zinc-600 uppercase mb-1">Detail Alamat Lengkap *</label>
              <textarea name="address" id="regAddress" rows="3" required placeholder="Nama Jalan, Gedung, RT/RW, Nomor Rumah" class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-red-600 outline-none leading-relaxed"></textarea>
            </div>
            <div class="pt-3 flex items-center justify-between">
              <button type="button" onclick="goToStep(2)" class="px-4 py-2 text-xs font-bold text-zinc-500 hover:text-zinc-800 transition cursor-pointer">&larr; Kembali</button>
              <button type="submit" class="px-6 py-2.5 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-xs transition uppercase tracking-wider cursor-pointer">
                Daftar Sekarang
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <script>
      function openSahabatRegisterModal() {
        document.getElementById('sahabatRegisterModal').classList.remove('hidden');
        goToStep(1);
      }

      function closeSahabatRegisterModal() {
        document.getElementById('sahabatRegisterModal').classList.add('hidden');
      }

      function goToStep(step) {
        document.getElementById('regStep1').classList.toggle('hidden', step !== 1);
        document.getElementById('regStep2').classList.toggle('hidden', step !== 2);
        document.getElementById('regStep3').classList.toggle('hidden', step !== 3);

        for (var i = 1; i <= 3; i++) {
          var pill = document.getElementById('stepPill' + i);
          if (pill) {
            if (i === step) {
              pill.className = 'py-2 px-2 text-center bg-[#CC0000] text-white font-bold rounded-xl transition text-[11px]';
            } else if (i < step) {
              pill.className = 'py-2 px-2 text-center bg-red-50 text-[#CC0000] font-bold rounded-xl transition text-[11px]';
            } else {
              pill.className = 'py-2 px-2 text-center bg-zinc-100 text-zinc-400 font-semibold rounded-xl transition text-[11px]';
            }
          }
        }
      }

      function validateAndNext(currentStep) {
        if (currentStep === 1) {
          var name = document.getElementById('regName').value.trim();
          var user = document.getElementById('regUsername').value.trim();
          var pass = document.getElementById('regPassword').value;
          var cpass = document.getElementById('regConfirmPassword').value;
          if (!name || !user || !pass || !cpass) {
            alert('Semua bidang pada Informasi Akun wajib diisi.');
            return;
          }
          if (pass !== cpass) {
            alert('Password dan Konfirmasi Password tidak cocok.');
            return;
          }
          if (pass.length < 6) {
            alert('Password minimal 6 karakter.');
            return;
          }
          goToStep(2);
        } else if (currentStep === 2) {
          var email = document.getElementById('regEmail').value.trim();
          var wa = document.getElementById('regWhatsapp').value.trim();
          var bdate = document.getElementById('regBirthDate').value;
          if (!email || !wa || !bdate) {
            alert('Semua bidang pada Data Pribadi & Kontak wajib diisi.');
            return;
          }
          if (email.indexOf('@') === -1) {
            alert('Format email tidak valid.');
            return;
          }
          goToStep(3);
        }
      }

      function handleGoogleLogin(response) {
        if (response && response.credential) {
          document.getElementById('googleCredential').value = response.credential;
          document.getElementById('googleForm').submit();
        }
      }

      window.addEventListener('load', function() {
        var clientId = '<?= htmlspecialchars($googleClientId) ?>';
        if (clientId && typeof google !== 'undefined' && google.accounts && google.accounts.id) {
          try {
            google.accounts.id.initialize({
              client_id: clientId,
              callback: handleGoogleLogin
            });
            google.accounts.id.renderButton(
              document.getElementById('googleOfficialBtn'),
              { theme: 'outline', size: 'large', width: '380', text: 'signin_with', shape: 'pill', logo_alignment: 'left' }
            );
            setTimeout(function() {
              var el = document.getElementById('googleOfficialBtn');
              if (el && el.children.length > 0) {
                var fallback = document.getElementById('googleSignInBtn');
                if (fallback) fallback.style.display = 'none';
              }
            }, 600);
          } catch(e) {}
        }
      });

      function triggerGoogleSignIn() {
        var clientId = '<?= htmlspecialchars($googleClientId) ?>';
        var btn = document.getElementById('googleSignInBtn');
        var origContent = btn ? btn.innerHTML : '';

        if (clientId && typeof google !== 'undefined' && google.accounts && google.accounts.oauth2) {
          try {
            if (btn) {
              btn.innerHTML = '<span class="inline-block animate-spin mr-2">&#9696;</span><span>Membuka Google Login...</span>';
              btn.disabled = true;
            }

            var tokenClient = google.accounts.oauth2.initTokenClient({
              client_id: clientId,
              scope: 'email profile openid',
              callback: async function(tokenResponse) {
                if (tokenResponse.error) {
                  if (btn) { btn.innerHTML = origContent; btn.disabled = false; }
                  return;
                }
                try {
                  var userInfo = await fetch('https://www.googleapis.com/oauth2/v3/userinfo', {
                    headers: { Authorization: 'Bearer ' + tokenResponse.access_token }
                  }).then(function(r) { return r.json(); });

                  if (userInfo && userInfo.email) {
                    document.getElementById('googleEmail').value = userInfo.email;
                    document.getElementById('googleName').value = userInfo.name || userInfo.email.split('@')[0];
                    document.getElementById('googleAvatar').value = userInfo.picture || '';
                    document.getElementById('googleForm').submit();
                  } else {
                    throw new Error('Gagal mendapatkan profil dari Google');
                  }
                } catch (err) {
                  if (btn) { btn.innerHTML = origContent; btn.disabled = false; }
                  alert('Gagal mengambil data akun Google: ' + err.message);
                }
              }
            });

            tokenClient.requestAccessToken({ prompt: 'select_account' });
            return;
          } catch(e) {
            console.error('Google OAuth init error:', e);
            if (btn) { btn.innerHTML = origContent; btn.disabled = false; }
          }
        } else {
          alert('Layanan Google Sign-In sedang dimuat, silakan klik lagi.');
        }
      }
    </script>

  <?php else: ?>
    <!-- STUDIO REDAKSI MAIN LAYOUT (MATCHING SCREENSHOT) -->
    <div class="min-h-screen flex">

      <!-- LEFT SIDEBAR FRAME: Pinned sticky h-screen top-0 -->
      <aside id="sidebar" class="w-64 bg-white border-r border-zinc-200 h-screen sticky top-0 flex flex-col justify-between shrink-0 transition-all duration-300 z-40 overflow-hidden select-none">
        <!-- Top Scrollable Container: Logo & Menus -->
        <div class="flex-1 overflow-y-auto overflow-x-hidden flex flex-col min-h-0">
          
          <!-- Sidebar Header: GNN Logo + Collapse Button (Matching media_1791791430911.png) -->
          <div class="h-[72px] px-4 border-b border-zinc-100 flex items-center justify-between shrink-0">
            <a href="/" class="flex items-center gap-2.5 overflow-hidden group">
              <div class="w-9 h-9 rounded-xl bg-[#CC0000] flex items-center justify-center shrink-0 shadow-xs">
                <span class="text-white font-black text-sm tracking-tighter">GNN</span>
              </div>
              <div class="sidebar-text flex flex-col leading-none">
                <span class="text-[13px] font-extrabold tracking-tight italic text-zinc-900 group-hover:text-red-700 transition">Good News</span>
                <span class="text-[9px] font-black tracking-widest text-white bg-[#CC0000] px-1.5 py-0.5 rounded-xs uppercase mt-0.5 inline-block text-center w-fit">Nusantara</span>
              </div>
            </a>
            <button onclick="toggleSidebar()" title="Kecilkan / Perbesar Menu" class="sidebar-text w-7 h-7 flex items-center justify-center rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition shrink-0">
              <svg id="sidebarToggleIcon" class="w-4 h-4 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
          </div>

          <!-- Section Label -->
          <div class="sidebar-text px-5 pt-6 pb-2 shrink-0">
            <span class="text-[10px] font-extrabold text-zinc-400 uppercase tracking-widest">MENU UTAMA STUDIO</span>
          </div>

          <!-- Navigation Menu Items -->
          <nav class="px-3 space-y-1 py-1 flex-1">
            
            <!-- 1. Artikel -->
            <?php $isActive = ($tab === 'articles' || $tab === 'write'); ?>
            <a href="?tab=articles" class="group flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs font-bold transition <?= $isActive ? 'bg-red-50 text-[#CC0000] border border-red-100' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 <?= $isActive ? 'text-[#CC0000]' : 'text-zinc-400 group-hover:text-zinc-600' ?> shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="sidebar-text">Artikel</span>
              </div>
              <?php if ($isActive): ?>
                <svg class="sidebar-text w-3.5 h-3.5 text-[#CC0000] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
              <?php endif; ?>
            </a>

            <!-- 2. Galeri Foto -->
            <?php $isActive = ($tab === 'gallery'); ?>
            <a href="?tab=gallery" class="group flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs font-bold transition <?= $isActive ? 'bg-red-50 text-[#CC0000] border border-red-100' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 <?= $isActive ? 'text-[#CC0000]' : 'text-zinc-400 group-hover:text-zinc-600' ?> shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="sidebar-text">Galeri Foto</span>
              </div>
              <?php if ($isActive): ?>
                <svg class="sidebar-text w-3.5 h-3.5 text-[#CC0000] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
              <?php endif; ?>
            </a>

            <!-- 3. Video YouTube -->
            <?php $isActive = ($tab === 'videos'); ?>
            <a href="?tab=videos" class="group flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs font-bold transition <?= $isActive ? 'bg-red-50 text-[#CC0000] border border-red-100' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 <?= $isActive ? 'text-[#CC0000]' : 'text-zinc-400 group-hover:text-zinc-600' ?> shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <span class="sidebar-text">Video YouTube</span>
              </div>
              <?php if ($isActive): ?>
                <svg class="sidebar-text w-3.5 h-3.5 text-[#CC0000] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
              <?php endif; ?>
            </a>

            <!-- 4. Pengguna -->
            <?php $isActive = ($tab === 'users'); ?>
            <a href="?tab=users" class="group flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs font-bold transition <?= $isActive ? 'bg-red-50 text-[#CC0000] border border-red-100' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 <?= $isActive ? 'text-[#CC0000]' : 'text-zinc-400 group-hover:text-zinc-600' ?> shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span class="sidebar-text">Pengguna</span>
              </div>
              <?php if ($isActive): ?>
                <svg class="sidebar-text w-3.5 h-3.5 text-[#CC0000] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
              <?php endif; ?>
            </a>

            <!-- 5. Pengaturan -->
            <?php $isActive = ($tab === 'settings'); ?>
            <a href="?tab=settings" class="group flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs font-bold transition <?= $isActive ? 'bg-red-50 text-[#CC0000] border border-red-100' : 'text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">
              <div class="flex items-center gap-3">
                <svg class="w-4 h-4 <?= $isActive ? 'text-[#CC0000]' : 'text-zinc-400 group-hover:text-zinc-600' ?> shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                <span class="sidebar-text">Pengaturan</span>
              </div>
              <?php if ($isActive): ?>
                <svg class="sidebar-text w-3.5 h-3.5 text-[#CC0000] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
              <?php endif; ?>
            </a>
          </nav>
        </div>

        <!-- Sidebar Footer: Pinned Profile Card Inside Studio Frame (Matching media_1791791647382.png) -->
        <div class="p-3 border-t border-zinc-100 bg-white shrink-0">
          <div id="sidebarUserCard" class="bg-zinc-50/90 border border-zinc-200/70 rounded-full sm:rounded-2xl p-2 sm:p-2.5 flex items-center gap-3 transition">
            <div class="relative shrink-0">
              <?php if (!empty($currentUser['avatar'])): ?>
                <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover border border-zinc-200 shadow-2xs">
              <?php else: ?>
                <div class="w-9 h-9 rounded-full bg-red-100 text-[#CC0000] font-black text-xs flex items-center justify-center border border-red-200 shadow-2xs">
                  <?= strtoupper(substr($currentUser['name'] ?: 'R', 0, 1)) ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="sidebar-text flex-1 min-w-0">
              <div class="text-xs font-bold text-zinc-900 truncate leading-snug"><?= htmlspecialchars($currentUser['name'] ?: 'Admin Utama GNN') ?></div>
              <div class="text-[10px] uppercase font-black text-[#CC0000] tracking-wider leading-none mt-0.5">
                <?= htmlspecialchars($currentUser['role'] ?: 'ADMIN') ?>
              </div>
            </div>
            <a href="?action=logout" title="Keluar / Logout" class="sidebar-text w-7 h-7 flex items-center justify-center rounded-lg text-zinc-400 hover:text-red-600 hover:bg-red-50 transition shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </a>
          </div>
        </div>
      </aside>

      <!-- MAIN CONTENT WRAPPER -->
      <div class="flex-1 flex flex-col min-w-0">
        
        <!-- TOP NAVIGATION BAR -->
        <header class="h-[72px] bg-white border-b border-zinc-200 px-6 sm:px-8 flex items-center justify-between sticky top-0 z-30">
          
          <!-- Left: Icon Badge & Title (Matching media_1791791444421.png & media_1791791458449.png) -->
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-zinc-100/90 border border-zinc-200/60 flex items-center justify-center text-[#CC0000] shrink-0 shadow-2xs">
              <?php if ($tab === 'gallery'): ?>
                <svg class="w-5 h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              <?php elseif ($tab === 'videos'): ?>
                <svg class="w-5 h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              <?php elseif ($tab === 'users'): ?>
                <svg class="w-5 h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
              <?php elseif ($tab === 'settings'): ?>
                <svg class="w-5 h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
              <?php else: ?>
                <svg class="w-5 h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              <?php endif; ?>
            </div>
            <h1 class="text-base sm:text-lg font-extrabold text-zinc-900 tracking-tight">
              <?php
                if ($tab === 'write') echo $editArticle ? 'Edit Artikel' : 'Tulis Artikel Baru';
                elseif ($tab === 'gallery') echo 'Galeri Foto Redaksi';
                elseif ($tab === 'videos') echo 'Kelola Video YouTube GNN TV';
                elseif ($tab === 'users') echo 'Kelola Tim & Pengguna';
                elseif ($tab === 'settings') echo 'Pengaturan Sistem Studio';
                else echo 'Kelola Artikel & Berita';
              ?>
            </h1>
          </div>

          <!-- Right: Search, Filter, Action Button (Matching screenshots) -->
          <div class="flex items-center gap-3">
            
            <?php if ($tab === 'articles'): ?>
              <!-- Search Input Pill -->
              <form method="GET" action="" class="relative">
                <input type="hidden" name="tab" value="articles">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                <input type="hidden" name="category" value="<?= htmlspecialchars($filterCategory) ?>">
                <div class="flex items-center bg-white border border-zinc-200 rounded-full px-3.5 py-2 text-xs text-zinc-700 shadow-xs focus-within:border-zinc-400 transition w-36 sm:w-52">
                  <svg class="w-3.5 h-3.5 text-zinc-400 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                  <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Cari..." class="bg-transparent outline-none w-full text-xs">
                  <?php if ($searchQuery): ?>
                    <a href="?tab=articles&status=<?= urlencode($filterStatus) ?>&category=<?= urlencode($filterCategory) ?>" class="text-zinc-400 hover:text-zinc-600 text-xs ml-1">&times;</a>
                  <?php endif; ?>
                </div>
              </form>

              <!-- Category Dropdown Pill (Kategori dari Navbar) -->
              <div class="relative">
                <select onchange="window.location.href='?tab=articles&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($filterStatus) ?>&category=' + encodeURIComponent(this.value)" class="appearance-none bg-white border border-zinc-200 rounded-full pl-3.5 pr-8 py-2 text-xs font-semibold text-zinc-700 shadow-xs outline-none cursor-pointer hover:border-zinc-300 transition">
                  <option value="all" <?= $filterCategory === 'all' ? 'selected' : '' ?>>Semua Kategori</option>
                  <?php foreach ($categoriesList as $cat): ?>
                    <option value="<?= $cat ?>" <?= (strcasecmp($filterCategory, $cat) === 0) ? 'selected' : '' ?>><?= $cat ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-zinc-400">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
              </div>

              <!-- Status Dropdown Pill -->
              <div class="relative">
                <select onchange="window.location.href='?tab=articles&q=<?= urlencode($searchQuery) ?>&category=<?= urlencode($filterCategory) ?>&status=' + this.value" class="appearance-none bg-white border border-zinc-200 rounded-full pl-3.5 pr-8 py-2 text-xs font-semibold text-zinc-700 shadow-xs outline-none cursor-pointer hover:border-zinc-300 transition">
                  <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                  <option value="publish" <?= $filterStatus === 'publish' ? 'selected' : '' ?>>Publish</option>
                  <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-zinc-400">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
              </div>

              <!-- Action Button: + Buat Artikel -->
              <a href="?tab=write" class="bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full px-4 sm:px-5 py-2.5 flex items-center gap-1.5 shadow-xs transition shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span>Buat Artikel</span>
              </a>
            
            <?php elseif ($tab === 'gallery'): ?>
              <!-- Search Gallery Pill (Matching media_1791791444421.png) -->
              <form method="GET" action="" class="relative">
                <input type="hidden" name="tab" value="gallery">
                <div class="flex items-center bg-white border border-zinc-200 rounded-full px-3.5 py-2 text-xs text-zinc-700 shadow-xs focus-within:border-zinc-400 transition w-44 sm:w-64">
                  <svg class="w-3.5 h-3.5 text-zinc-400 mr-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                  <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Cari di galeri foto..." class="bg-transparent outline-none w-full text-xs placeholder:text-zinc-400">
                  <?php if ($searchQuery): ?>
                    <a href="?tab=gallery" class="text-zinc-400 hover:text-zinc-600 text-xs ml-1">&times;</a>
                  <?php endif; ?>
                </div>
              </form>

              <!-- Button: + Unggah Foto (Matching media_1791791444421.png) -->
              <button onclick="document.getElementById('modalAddGallery').classList.remove('hidden')" class="bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full px-4 sm:px-5 py-2.5 flex items-center gap-1.5 shadow-xs transition shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span>Unggah Foto</span>
              </button>

            <?php elseif ($tab === 'videos'): ?>
              <!-- Search Video Pill (Matching media_1791791458449.png) -->
              <form method="GET" action="" class="relative">
                <input type="hidden" name="tab" value="videos">
                <div class="flex items-center bg-white border border-zinc-200 rounded-full px-3.5 py-2 text-xs text-zinc-700 shadow-xs focus-within:border-zinc-400 transition w-44 sm:w-64">
                  <svg class="w-3.5 h-3.5 text-zinc-400 mr-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                  <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Cari video YouTube..." class="bg-transparent outline-none w-full text-xs placeholder:text-zinc-400">
                  <?php if ($searchQuery): ?>
                    <a href="?tab=videos" class="text-zinc-400 hover:text-zinc-600 text-xs ml-1">&times;</a>
                  <?php endif; ?>
                </div>
              </form>

              <!-- Button: + Tambah Video (Matching media_1791791458449.png) -->
              <button onclick="document.getElementById('modalAddVideo').classList.remove('hidden')" class="bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full px-4 sm:px-5 py-2.5 flex items-center gap-1.5 shadow-xs transition shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span>Tambah Video</span>
              </button>

            <?php elseif ($tab === 'write'): ?>
              <a href="?tab=articles" class="px-4 py-2 border border-zinc-200 rounded-full text-xs font-bold text-zinc-600 hover:bg-zinc-50 transition">
                &larr; Kembali ke Daftar
              </a>
            <?php endif; ?>

          </div>
        </header>

        <!-- FLASH NOTIFICATIONS -->
        <div class="px-6 sm:px-8 pt-4">
          <?php if (!empty($msg) || !empty($_GET['msg'])): ?>
            <div class="bg-green-50 text-green-800 p-3.5 rounded-2xl border border-green-200 text-xs font-bold flex items-center justify-between">
              <span><?= htmlspecialchars($msg ?: $_GET['msg']) ?></span>
              <button onclick="this.parentElement.remove()" class="text-green-600 font-black text-sm">&times;</button>
            </div>
          <?php endif; ?>
          <?php if (!empty($err)): ?>
            <div class="bg-red-50 text-red-800 p-3.5 rounded-2xl border border-red-200 text-xs font-bold flex items-center justify-between">
              <span><?= htmlspecialchars($err) ?></span>
              <button onclick="this.parentElement.remove()" class="text-red-600 font-black text-sm">&times;</button>
            </div>
          <?php endif; ?>
        </div>

        <!-- MAIN BODY VIEW -->
        <main class="p-6 sm:p-8 flex-1">

          <!-- TAB: ARTICLES -->
          <?php if ($tab === 'articles'): ?>
            <div class="bg-white rounded-3xl border border-zinc-200/80 shadow-xs p-6 sm:p-8 min-h-[480px] flex flex-col justify-between">
              
              <?php if (empty($articles)): ?>
                <!-- EMPTY STATE (MATCHING SCREENSHOT) -->
                <div class="my-auto py-16 flex flex-col items-center justify-center text-center">
                  <div class="w-16 h-16 rounded-full border-2 border-zinc-300 flex items-center justify-center text-zinc-300 text-2xl font-bold mb-4 select-none">
                    !
                  </div>
                  <p class="text-zinc-500 font-medium text-sm">Tidak ada artikel yang ditemukan.</p>
                </div>
              <?php else: ?>
                <!-- ARTICLES DATA TABLE -->
                <div class="overflow-x-auto">
                  <table class="w-full text-left text-xs text-zinc-700">
                    <thead>
                      <tr class="border-b border-zinc-100 text-[11px] font-bold text-zinc-400 uppercase tracking-wider">
                        <th class="pb-3 px-2">Artikel</th>
                        <th class="pb-3 px-2">Kategori</th>
                        <th class="pb-3 px-2">Penulis</th>
                        <th class="pb-3 px-2">Status</th>
                        <th class="pb-3 px-2">Pembaca</th>
                        <th class="pb-3 px-2 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                      <?php foreach ($articles as $art): ?>
                        <tr class="hover:bg-zinc-50/70 transition group">
                          <!-- Image + Title -->
                          <td class="py-3 px-2 max-w-sm">
                            <div class="flex items-center gap-3">
                              <?php if (!empty($art['image'])): ?>
                                <img src="<?= htmlspecialchars($art['image']) ?>" alt="Cover" class="w-12 h-12 rounded-xl object-cover border border-zinc-100 shrink-0">
                              <?php else: ?>
                                <div class="w-12 h-12 rounded-xl bg-zinc-100 text-zinc-400 flex items-center justify-center font-bold shrink-0">G</div>
                              <?php endif; ?>
                              <div>
                                <a href="?edit_art=<?= urlencode($art['id']) ?>" class="font-bold text-zinc-900 group-hover:text-[#CC0000] transition line-clamp-2">
                                  <?= htmlspecialchars($art['title']) ?>
                                </a>
                                <span class="text-[10px] text-zinc-400"><?= htmlspecialchars($art['date'] ?: date('d M Y', strtotime($art['created_at']))) ?></span>
                              </div>
                            </div>
                          </td>
                          <!-- Category -->
                          <td class="py-3 px-2">
                            <span class="px-2.5 py-1 bg-red-50 text-[#CC0000] rounded-lg font-bold text-[10px]">
                              <?= htmlspecialchars($art['category']) ?>
                            </span>
                          </td>
                          <!-- Author -->
                          <td class="py-3 px-2 font-medium text-zinc-600">
                            <?= htmlspecialchars($art['author'] ?: 'Redaksi GNN') ?>
                          </td>
                          <!-- Status -->
                          <td class="py-3 px-2">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase <?= ($art['status'] === 'publish') ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-700 border border-amber-100' ?>">
                              <?= strtoupper($art['status'] ?: 'PUBLISH') ?>
                            </span>
                          </td>
                          <!-- Views -->
                          <td class="py-3 px-2 font-semibold text-zinc-500">
                            <?= number_format($art['views']) ?> views
                          </td>
                          <!-- Action Buttons -->
                          <td class="py-3 px-2 text-right">
                            <div class="inline-flex items-center gap-1.5">
                              <a href="?edit_art=<?= urlencode($art['id']) ?>" title="Edit" class="p-1.5 rounded-lg text-zinc-400 hover:text-blue-600 hover:bg-blue-50 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                              </a>
                              <a href="/?article=<?= urlencode($art['id']) ?>" target="_blank" title="Lihat di Web" class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-800 hover:bg-zinc-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                              </a>
                              <a href="?delete_art=<?= urlencode($art['id']) ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus artikel ini?')" title="Hapus" class="p-1.5 rounded-lg text-zinc-400 hover:text-red-600 hover:bg-red-50 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                              </a>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Footer Summary -->
                <div class="mt-6 pt-4 border-t border-zinc-100 flex items-center justify-between text-xs text-zinc-400 font-medium">
                  <span>Menampilkan <strong><?= count($articles) ?></strong> dari <strong><?= $totalArticlesCount ?></strong> total artikel</span>
                  <span>Good News Nusantara Studio Redaksi</span>
                </div>
              <?php endif; ?>

            </div>

          <!-- TAB: WRITE / EDIT ARTICLE -->
          <?php elseif ($tab === 'write'): ?>
            <div class="max-w-5xl mx-auto bg-white rounded-3xl border border-zinc-200/80 shadow-xs p-6 sm:p-8">
              
              <!-- Form Header -->
              <div class="flex items-center justify-between pb-4 mb-6 border-b border-zinc-100">
                <div>
                  <h2 class="font-extrabold text-xl text-zinc-900 tracking-tight">
                    <?= $editArticle ? 'Edit Naskah Artikel' : 'Tulis Artikel Baru' ?>
                  </h2>
                  <p class="text-xs text-zinc-500 mt-0.5">Kelola penerbitan kabar baik, karya, dan inspirasi Nusantara</p>
                </div>
                <a href="?tab=articles" class="px-4 py-2 border border-zinc-200 hover:bg-zinc-50 rounded-full text-xs font-bold text-zinc-600 transition flex items-center gap-1.5 shadow-2xs">
                  <span>&times;</span>
                  <span>Batal &amp; Tutup</span>
                </a>
              </div>

              <form method="POST" action="" class="space-y-6" id="articleWriteForm">
                <input type="hidden" name="action" value="save_article">
                <input type="hidden" name="id" value="<?= htmlspecialchars($editArticle['id'] ?? '') ?>">

                <!-- 1. Judul Artikel -->
                <div>
                  <label class="block text-xs font-bold text-zinc-800 uppercase tracking-wider mb-1.5">
                    Judul Artikel <span class="text-[#CC0000]">*</span>
                  </label>
                  <input type="text" name="title" required value="<?= htmlspecialchars($editArticle['title'] ?? '') ?>" placeholder="Masukkan judul artikel yang menarik dan inspiratif..." class="w-full px-4 py-3 text-sm sm:text-base font-bold border border-zinc-200 rounded-2xl focus:border-[#CC0000] focus:ring-1 focus:ring-[#CC0000] outline-none transition placeholder-zinc-400">
                </div>

                <!-- 2. Kategori, Sub Kategori & Lokasi -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <!-- Kategori -->
                  <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase tracking-wider mb-1.5">
                      Kategori Artikel <span class="text-[#CC0000]">*</span>
                    </label>
                    <select id="selectCategory" name="category" onchange="updateSubCategories()" class="w-full px-3.5 py-2.5 text-xs font-bold border border-zinc-200 rounded-2xl focus:border-[#CC0000] outline-none bg-white cursor-pointer">
                      <?php foreach ($navbarCategories as $mainCat => $subs): ?>
                        <option value="<?= $mainCat ?>" <?= (strcasecmp($editArticle['category'] ?? 'Ekonomi', $mainCat) === 0) ? 'selected' : '' ?>><?= $mainCat ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <!-- Sub Kategori -->
                  <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase tracking-wider mb-1.5">
                      Sub Kategori <span class="text-zinc-400 font-normal lowercase">(Opsional)</span>
                    </label>
                    <select id="selectSubCategory" name="sub_category" class="w-full px-3.5 py-2.5 text-xs font-bold border border-zinc-200 rounded-2xl focus:border-[#CC0000] outline-none bg-white cursor-pointer">
                      <!-- Populated dynamically via JS -->
                    </select>
                  </div>

                  <!-- Lokasi Peliputan / Berita -->
                  <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase tracking-wider mb-1.5">
                      Lokasi Peliputan / Berita <span class="text-[#CC0000]">*</span>
                    </label>
                    <input type="text" name="location" required value="<?= htmlspecialchars($editArticle['location'] ?? 'Jakarta') ?>" placeholder="Contoh: Jakarta, Surabaya, Jayapura" class="w-full px-3.5 py-2.5 text-xs font-semibold border border-zinc-200 rounded-2xl focus:border-[#CC0000] outline-none">
                  </div>
                </div>

                <!-- 3. Foto Cover Artikel (Preview, Unggah / Galeri, Potong, Caption & Kredit) -->
                <div class="border border-zinc-200 rounded-3xl p-5 bg-zinc-50/60 space-y-4">
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-zinc-200/80">
                    <div>
                      <label class="text-xs font-bold text-zinc-800 uppercase tracking-wider block">
                        Foto Cover Artikel <span class="text-[#CC0000]">*</span>
                      </label>
                      <p class="text-[11px] text-zinc-500">Rasio ideal 16:9 lanskap resolusi tinggi</p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 flex-wrap">
                      <button type="button" onclick="document.getElementById('fileUploadCover').click()" class="px-3 py-1.5 bg-white border border-zinc-200 hover:border-zinc-300 text-zinc-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <span>Unggah Gambar Baru</span>
                      </button>
                      <input type="file" id="fileUploadCover" accept="image/*" class="hidden" onchange="handleCoverFileUpload(this)">

                      <button type="button" onclick="openGalleryPickerModal()" class="px-3 py-1.5 bg-white border border-zinc-200 hover:border-zinc-300 text-zinc-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Pilih dari Galeri Foto</span>
                      </button>

                      <button type="button" onclick="openCropRatioModal()" class="px-3 py-1.5 bg-red-50 text-[#CC0000] border border-red-200 hover:bg-red-100 text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v16h16M8 4v8a2 2 0 002 2h8"/></svg>
                        <span>Potong &amp; Atur Foto</span>
                      </button>
                    </div>
                  </div>

                  <!-- URL Input (Synchronized) -->
                  <div>
                    <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wider mb-1">URL Cover Gambar</label>
                    <input type="url" name="image" id="artImageInput" required value="<?= htmlspecialchars($editArticle['image'] ?? 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80') ?>" placeholder="https://images.unsplash.com/..." class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none bg-white font-mono" oninput="updateCoverPreview()">
                  </div>

                  <!-- Cover Preview Box -->
                  <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                    <div class="md:col-span-5 aspect-video bg-zinc-200 rounded-2xl overflow-hidden border border-zinc-200 relative group shadow-2xs">
                      <img id="coverPreviewImg" src="<?= htmlspecialchars($editArticle['image'] ?? 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80') ?>" alt="Cover Preview" class="w-full h-full object-cover transition duration-300 group-hover:scale-102" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80';">
                      <div class="absolute bottom-2 left-2 bg-black/70 backdrop-blur-xs text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider">
                        16:9 Cover Preview
                      </div>
                    </div>

                    <!-- Caption & Image Credit -->
                    <div class="md:col-span-7 space-y-3">
                      <div>
                        <label class="block text-[11px] font-bold text-zinc-700 uppercase tracking-wider mb-1">
                          Nama / Judul Foto (Caption)
                        </label>
                        <input type="text" name="caption" id="inputCaption" value="<?= htmlspecialchars($editArticle['caption'] ?? '') ?>" placeholder="Keterangan peristiwa / objek dokumentasi foto" class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none bg-white">
                      </div>

                      <div>
                        <label class="block text-[11px] font-bold text-zinc-700 uppercase tracking-wider mb-1">
                          Kredit Foto (Fotografer / Sumber)
                        </label>
                        <input type="text" name="image_credit" id="inputImageCredit" value="<?= htmlspecialchars($editArticle['image_credit'] ?? 'Dok. Redaksi Good News Nusantara') ?>" placeholder="Contoh: Dok. Redaksi GNN / Unsplash / Antara Foto" class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none bg-white">
                      </div>
                    </div>
                  </div>
                </div>

                <!-- 4. Isi Artikel (WYSIWYG Tampilan dengan Toolbar Lengkap) -->
                <div>
                  <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold text-zinc-800 uppercase tracking-wider">
                      Isi Artikel <span class="text-[#CC0000]">*</span> <span class="text-zinc-400 font-normal lowercase">(Tampilan WYSIWYG)</span>
                    </label>
                    <span class="text-[11px] text-zinc-400 font-semibold" id="wordCountLabel">0 kata</span>
                  </div>

                  <!-- WYSIWYG Toolbar -->
                  <div class="border border-zinc-200 rounded-t-2xl bg-zinc-100/80 p-2 flex items-center flex-wrap gap-1 border-b-0 shadow-2xs">
                    <!-- Heading H2 -->
                    <button type="button" onclick="formatEditor('formatBlock', '<h2>')" class="px-2.5 py-1.5 bg-white hover:bg-zinc-200 border border-zinc-200 rounded-lg text-xs font-bold text-zinc-700 transition" title="Heading 2">
                      H2
                    </button>

                    <!-- Heading H3 -->
                    <button type="button" onclick="formatEditor('formatBlock', '<h3>')" class="px-2.5 py-1.5 bg-white hover:bg-zinc-200 border border-zinc-200 rounded-lg text-xs font-bold text-zinc-700 transition" title="Heading 3">
                      H3
                    </button>

                    <div class="h-5 w-[1px] bg-zinc-300 mx-1"></div>

                    <!-- Bold -->
                    <button type="button" onclick="formatEditor('bold')" class="p-1.5 bg-white hover:bg-zinc-200 border border-zinc-200 rounded-lg text-xs font-black text-zinc-700 transition w-7 h-7 flex items-center justify-center" title="Tebal (Bold)">
                      B
                    </button>

                    <!-- Italic -->
                    <button type="button" onclick="formatEditor('italic')" class="p-1.5 bg-white hover:bg-zinc-200 border border-zinc-200 rounded-lg text-xs italic font-serif text-zinc-700 transition w-7 h-7 flex items-center justify-center" title="Miring (Italic)">
                      I
                    </button>

                    <!-- Quote Block -->
                    <button type="button" onclick="formatEditor('formatBlock', '<blockquote>')" class="px-2 py-1 bg-white hover:bg-zinc-200 border border-zinc-200 rounded-lg text-xs font-bold text-zinc-700 transition" title="Kutipan">
                      &ldquo;&rdquo;
                    </button>

                    <!-- Bullet List -->
                    <button type="button" onclick="formatEditor('insertUnorderedList')" class="p-1.5 bg-white hover:bg-zinc-200 border border-zinc-200 rounded-lg text-xs font-bold text-zinc-700 transition w-7 h-7 flex items-center justify-center" title="Daftar Poin">
                      &bull;
                    </button>

                    <div class="h-5 w-[1px] bg-zinc-300 mx-1"></div>

                    <!-- Sisipkan Foto -->
                    <button type="button" onclick="insertArticleImage()" class="px-2.5 py-1 bg-white hover:bg-zinc-200 border border-zinc-200 rounded-lg text-xs font-bold text-zinc-700 transition flex items-center gap-1.5" title="Sisipkan Foto ke Dalam Naskah">
                      <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                      <span>Sisipkan Foto</span>
                    </button>

                    <!-- Sisipkan Media Sosial -->
                    <button type="button" onclick="insertSocialEmbed()" class="px-2.5 py-1 bg-white hover:bg-zinc-200 border border-zinc-200 rounded-lg text-xs font-bold text-zinc-700 transition flex items-center gap-1.5" title="Sisipkan Media Sosial (Embed Instagram / Twitter / YouTube)">
                      <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                      <span>Sisipkan Media Sosial</span>
                    </button>

                    <div class="h-5 w-[1px] bg-zinc-300 mx-1"></div>

                    <!-- Poin Utama / Fokus Cepat -->
                    <button type="button" onclick="toggleBulletsHelper()" class="px-2.5 py-1 bg-white hover:bg-zinc-200 border border-zinc-200 rounded-lg text-xs font-bold text-zinc-700 transition" title="Atur Poin Utama Artikel">
                      Poin Ringkasan
                    </button>
                  </div>

                  <!-- WYSIWYG Editable Document Workspace -->
                  <div id="editorContent" contenteditable="true" class="min-h-[340px] max-h-[640px] overflow-y-auto p-5 border border-zinc-200 rounded-b-2xl bg-white text-zinc-900 leading-relaxed font-sans focus:outline-none focus:border-[#CC0000] focus:ring-1 focus:ring-[#CC0000] prose prose-zinc max-w-none text-sm" oninput="syncEditorToTextarea()"><?= $editArticle['content'] ?? '<p>Tuliskan naskah berita secara lengkap, akurat, dan mengalir di sini...</p>' ?></div>

                  <!-- Hidden Textarea for Form POST Sync -->
                  <textarea name="content" id="hiddenContentTextarea" required class="hidden"><?= htmlspecialchars($editArticle['content'] ?? '') ?></textarea>
                </div>

                <!-- Bullets / Poin Ringkasan (Expandable) -->
                <div id="bulletsContainer" class="p-4 border border-zinc-200 rounded-2xl bg-zinc-50/70 space-y-2">
                  <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider">
                      Poin Utama / Ringkasan Dokumen (1 Poin Per Baris)
                    </label>
                    <span class="text-[10px] text-zinc-400">Otomatis tampil rapi di ringkasan berita</span>
                  </div>
                  <textarea name="bullets" rows="3" placeholder="Poin penting pertama&#10;Poin penting kedua&#10;Poin penting ketiga" class="w-full px-4 py-2.5 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none font-mono bg-white"><?= htmlspecialchars($editArticle['bullets_text'] ?? '') ?></textarea>
                </div>

                <!-- 5. Kata Kunci / Tag -->
                <div>
                  <label class="block text-xs font-bold text-zinc-800 uppercase tracking-wider mb-1.5">
                    Kata Kunci / Tag (Pisahkan dengan koma)
                  </label>
                  <input type="text" name="keywords" value="<?= htmlspecialchars($editArticle['keywords'] ?? 'kabar baik, indonesia, inovasi nusantara') ?>" placeholder="Contoh: bromo, pariwisata, jawa timur, inspirasi indonesia" class="w-full px-4 py-2.5 text-xs font-semibold border border-zinc-200 rounded-2xl focus:border-[#CC0000] outline-none">
                </div>

                <!-- 6. Penulis, Status Penerbitan & User Notice -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                  <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase tracking-wider mb-1.5">Nama Penulis</label>
                    <input type="text" name="author" value="<?= htmlspecialchars($editArticle['author'] ?? $currentUser['name']) ?>" class="w-full px-4 py-2.5 text-xs font-bold border border-zinc-200 rounded-2xl focus:border-[#CC0000] outline-none bg-zinc-50">
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase tracking-wider mb-1.5">Status Penerbitan</label>
                    <select name="status" class="w-full px-4 py-2.5 text-xs font-bold border border-zinc-200 rounded-2xl focus:border-[#CC0000] outline-none bg-white cursor-pointer">
                      <option value="publish" <?= ($editArticle['status'] ?? 'publish') === 'publish' ? 'selected' : '' ?>>Terbitkan (Publish)</option>
                      <option value="draft" <?= ($editArticle['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Simpan Sebagai Draf (Draft)</option>
                    </select>
                  </div>
                </div>

                <!-- User Notice Box -->
                <div class="p-3.5 rounded-2xl bg-red-50/70 border border-red-100 flex items-center justify-between text-xs">
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-zinc-600">
                      Anda masuk sebagai <strong><?= htmlspecialchars($currentUser['name']) ?></strong> (<?= strtoupper($currentUser['role'] ?? 'ADMIN') ?>)
                    </span>
                  </div>
                  <span class="text-[10px] text-zinc-400 font-mono">ID: <?= htmlspecialchars($currentUser['id']) ?></span>
                </div>

                <!-- Footer Action Buttons -->
                <div class="pt-6 border-t border-zinc-100 flex items-center justify-between">
                  <a href="?tab=articles" class="text-xs font-bold text-zinc-400 hover:text-zinc-700 transition">
                    &larr; Batal &amp; Kembali
                  </a>
                  <div class="flex items-center gap-3">
                    <button type="button" onclick="document.querySelector('select[name=status]').value='draft'; document.getElementById('articleWriteForm').submit();" class="px-5 py-2.5 border border-zinc-200 text-zinc-700 hover:bg-zinc-50 font-bold text-xs rounded-full shadow-2xs transition">
                      Simpan Draf
                    </button>
                    <button type="submit" class="px-7 py-3 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full shadow-sm transition uppercase tracking-wider">
                      Terbitkan Sekarang
                    </button>
                  </div>
                </div>
              </form>
            </div>

            <!-- MODAL: PILIH DARI GALERI FOTO -->
            <div id="modalGalleryPicker" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
              <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-4 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100 shrink-0">
                  <div>
                    <h3 class="font-extrabold text-base text-zinc-900">Pilih Foto dari Galeri Redaksi</h3>
                    <p class="text-xs text-zinc-500">Klik salah satu foto untuk menjadikannya foto cover artikel</p>
                  </div>
                  <button type="button" onclick="closeGalleryPickerModal()" class="text-zinc-400 hover:text-zinc-700 text-xl font-bold">&times;</button>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 overflow-y-auto flex-1 p-1">
                  <?php foreach ($gallery as $gItem): ?>
                    <div onclick="selectGalleryPhoto('<?= htmlspecialchars($gItem['image_url'], ENT_QUOTES) ?>', '<?= htmlspecialchars($gItem['caption'] ?? $gItem['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($gItem['author'] ?? 'Redaksi GNN', ENT_QUOTES) ?>')" class="cursor-pointer group rounded-xl overflow-hidden border border-zinc-200 hover:border-[#CC0000] transition relative aspect-video bg-zinc-100">
                      <img src="<?= htmlspecialchars($gItem['image_url']) ?>" alt="<?= htmlspecialchars($gItem['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition">
                      <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent flex items-end p-2 opacity-90 group-hover:opacity-100">
                        <span class="text-white text-[10px] font-bold line-clamp-1"><?= htmlspecialchars($gItem['title']) ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  <?php if (empty($gallery)): ?>
                    <div class="col-span-3 py-12 text-center text-xs text-zinc-400">
                      Belum ada foto tersimpan di galeri. Silakan gunakan tombol Unggah Gambar.
                    </div>
                  <?php endif; ?>
                </div>
                <div class="pt-2 border-t border-zinc-100 flex justify-end shrink-0">
                  <button type="button" onclick="closeGalleryPickerModal()" class="px-4 py-2 text-xs font-bold text-zinc-500 hover:text-zinc-800">Tutup</button>
                </div>
              </div>
            </div>

            <!-- MODAL: POTONG & ATUR RASIO FOTO -->
            <div id="modalCropRatio" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4 hidden">
              <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                  <h3 class="font-extrabold text-base text-zinc-900">Potong &amp; Atur Rasio Foto Cover</h3>
                  <button type="button" onclick="closeCropRatioModal()" class="text-zinc-400 hover:text-zinc-700 text-xl font-bold">&times;</button>
                </div>
                <div class="space-y-3">
                  <p class="text-xs text-zinc-600">Pilih orientasi posisi fokus gambar untuk format standar portal web (16:9):</p>
                  
                  <div class="aspect-video bg-zinc-200 rounded-2xl overflow-hidden border border-zinc-300 relative shadow-inner">
                    <img id="cropModalPreview" src="" alt="Preview" class="w-full h-full object-cover">
                  </div>

                  <div class="grid grid-cols-3 gap-2 pt-2">
                    <button type="button" onclick="setCoverObjectPosition('center')" class="px-3 py-2 border border-zinc-200 rounded-xl text-xs font-bold text-zinc-700 hover:border-[#CC0000] focus:border-[#CC0000] focus:bg-red-50">
                      Tengah (Center)
                    </button>
                    <button type="button" onclick="setCoverObjectPosition('top')" class="px-3 py-2 border border-zinc-200 rounded-xl text-xs font-bold text-zinc-700 hover:border-[#CC0000] focus:border-[#CC0000] focus:bg-red-50">
                      Atas (Top)
                    </button>
                    <button type="button" onclick="setCoverObjectPosition('bottom')" class="px-3 py-2 border border-zinc-200 rounded-xl text-xs font-bold text-zinc-700 hover:border-[#CC0000] focus:border-[#CC0000] focus:bg-red-50">
                      Bawah (Bottom)
                    </button>
                  </div>
                </div>
                <div class="pt-4 border-t border-zinc-100 flex justify-end gap-2">
                  <button type="button" onclick="closeCropRatioModal()" class="px-4 py-2 text-xs font-bold text-zinc-500 hover:text-zinc-800">Batal</button>
                  <button type="button" onclick="applyCropSetting()" class="px-5 py-2 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full">Terapkan Pengaturan</button>
                </div>
              </div>
            </div>

            <!-- SCRIPTS FOR WYSIWYG & PHOTO HANDLER -->
            <script>
              const navbarCategoryData = <?= json_encode($navbarCategories) ?>;
              const currentCategoryVal = '<?= htmlspecialchars($editArticle['category'] ?? 'Ekonomi') ?>';
              const currentSubCategoryVal = '<?= htmlspecialchars($editArticle['sub_category'] ?? '') ?>';

              function updateSubCategories() {
                const selCat = document.getElementById('selectCategory');
                const selSub = document.getElementById('selectSubCategory');
                const chosen = selCat.value;
                const subs = navbarCategoryData[chosen] || [];

                selSub.innerHTML = '';
                const defOpt = document.createElement('option');
                defOpt.value = chosen;
                defOpt.textContent = chosen + ' (Utama)';
                selSub.appendChild(defOpt);

                subs.forEach(s => {
                  if (s.toLowerCase() !== chosen.toLowerCase()) {
                    const opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    if (s.toLowerCase() === currentSubCategoryVal.toLowerCase()) {
                      opt.selected = true;
                    }
                    selSub.appendChild(opt);
                  }
                });
              }

              // Initialize on load
              document.addEventListener('DOMContentLoaded', () => {
                updateSubCategories();
                syncEditorToTextarea();
                updateWordCount();
              });

              // WYSIWYG functions
              function formatEditor(command, val = null) {
                document.getElementById('editorContent').focus();
                document.execCommand(command, false, val);
                syncEditorToTextarea();
              }

              function syncEditorToTextarea() {
                const editor = document.getElementById('editorContent');
                const textarea = document.getElementById('hiddenContentTextarea');
                if (editor && textarea) {
                  textarea.value = editor.innerHTML;
                  updateWordCount();
                }
              }

              function updateWordCount() {
                const editor = document.getElementById('editorContent');
                const label = document.getElementById('wordCountLabel');
                if (editor && label) {
                  const text = editor.innerText.trim();
                  const words = text ? text.split(/\s+/).length : 0;
                  label.textContent = words + ' kata';
                }
              }

              function insertArticleImage() {
                const url = prompt('Masukkan URL Gambar / Foto yang ingin disisipkan:', 'https://images.unsplash.com/');
                if (url) {
                  const caption = prompt('Keterangan foto (opsional):', '');
                  const imgHtml = `<figure class="my-4"><img src="${url}" alt="${caption}" class="w-full rounded-2xl border border-zinc-200" /><figcaption class="text-xs text-zinc-500 mt-1 italic text-center">${caption || ''}</figcaption></figure><p></p>`;
                  document.getElementById('editorContent').focus();
                  document.execCommand('insertHTML', false, imgHtml);
                  syncEditorToTextarea();
                }
              }

              function insertSocialEmbed() {
                const url = prompt('Masukkan URL media sosial atau YouTube:', 'https://www.youtube.com/watch?v=');
                if (url) {
                  const embedHtml = `<div class="p-4 my-4 bg-zinc-50 border border-zinc-200 rounded-2xl text-xs"><a href="${url}" target="_blank" class="font-bold text-[#CC0000] underline flex items-center gap-1.5">🔗 Lihat Postingan Terkait (${url})</a></div><p></p>`;
                  document.getElementById('editorContent').focus();
                  document.execCommand('insertHTML', false, embedHtml);
                  syncEditorToTextarea();
                }
              }

              function toggleBulletsHelper() {
                const container = document.getElementById('bulletsContainer');
                container.classList.toggle('hidden');
              }

              // Cover image previews
              function updateCoverPreview() {
                const val = document.getElementById('artImageInput').value.trim();
                const preview = document.getElementById('coverPreviewImg');
                if (val && preview) {
                  preview.src = val;
                }
              }

              function handleCoverFileUpload(input) {
                if (input.files && input.files[0]) {
                  const file = input.files[0];
                  const reader = new FileReader();
                  reader.onload = function(e) {
                    const dataUrl = e.target.result;
                    document.getElementById('artImageInput').value = dataUrl;
                    document.getElementById('coverPreviewImg').src = dataUrl;
                  };
                  reader.readAsDataURL(file);
                }
              }

              // Gallery Picker
              function openGalleryPickerModal() {
                document.getElementById('modalGalleryPicker').classList.remove('hidden');
              }
              function closeGalleryPickerModal() {
                document.getElementById('modalGalleryPicker').classList.add('hidden');
              }
              function selectGalleryPhoto(url, caption, credit) {
                document.getElementById('artImageInput').value = url;
                document.getElementById('coverPreviewImg').src = url;
                if (caption) document.getElementById('inputCaption').value = caption;
                if (credit) document.getElementById('inputImageCredit').value = credit;
                closeGalleryPickerModal();
              }

              // Crop Modal
              function openCropRatioModal() {
                const currentImg = document.getElementById('artImageInput').value;
                document.getElementById('cropModalPreview').src = currentImg;
                document.getElementById('modalCropRatio').classList.remove('hidden');
              }
              function closeCropRatioModal() {
                document.getElementById('modalCropRatio').classList.add('hidden');
              }
              let chosenObjectPos = 'center';
              function setCoverObjectPosition(pos) {
                chosenObjectPos = pos;
                document.getElementById('cropModalPreview').style.objectPosition = pos;
              }
              function applyCropSetting() {
                document.getElementById('coverPreviewImg').style.objectPosition = chosenObjectPos;
                closeCropRatioModal();
              }
            </script>

          <!-- TAB: GALERI FOTO -->
          <?php elseif ($tab === 'gallery'): ?>
            <div class="bg-white rounded-3xl border border-zinc-200/80 shadow-xs p-6 sm:p-8 min-h-[480px]">
              <div class="flex items-center justify-between mb-6 pb-4 border-b border-zinc-100">
                <div>
                  <h2 class="font-extrabold text-lg text-zinc-900">Koleksi Galeri Foto (<?= count($gallery) ?>)</h2>
                  <p class="text-xs text-zinc-500">Koleksi dokumentasi visual dan kekayaan fotografi Nusantara</p>
                </div>
                <button onclick="document.getElementById('modalAddGallery').classList.remove('hidden')" class="px-4 py-2 bg-[#CC0000] text-white text-xs font-bold rounded-full hover:bg-red-700 transition">
                  + Tambah Foto
                </button>
              </div>

              <?php if (empty($gallery)): ?>
                <div class="py-16 text-center text-zinc-400">
                  <p class="text-sm">Belum ada foto di galeri.</p>
                </div>
              <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                  <?php foreach ($gallery as $gal): ?>
                    <div class="group relative rounded-2xl overflow-hidden border border-zinc-200 shadow-xs bg-zinc-50 flex flex-col justify-between">
                      <div class="aspect-4/3 w-full overflow-hidden bg-zinc-100">
                        <img src="<?= htmlspecialchars($gal['image_url']) ?>" alt="<?= htmlspecialchars($gal['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                      </div>
                      <div class="p-3 bg-white flex items-center justify-between gap-2">
                        <div class="truncate">
                          <div class="font-bold text-xs text-zinc-900 truncate"><?= htmlspecialchars($gal['title']) ?></div>
                          <div class="text-[10px] text-zinc-400"><?= htmlspecialchars($gal['author'] ?: 'Redaksi GNN') ?></div>
                        </div>
                        <a href="?delete_gal=<?= urlencode($gal['id']) ?>" onclick="return confirm('Hapus foto ini?')" class="p-1 text-zinc-400 hover:text-red-600 transition">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Modal Add Gallery -->
            <div id="modalAddGallery" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
              <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-zinc-100">
                  <h3 class="font-bold text-base text-zinc-900">+ Unggah / Tambah Foto Baru</h3>
                  <button onclick="document.getElementById('modalAddGallery').classList.add('hidden')" class="text-zinc-400 hover:text-zinc-700 text-lg">&times;</button>
                </div>
                <form method="POST" action="" class="space-y-3.5">
                  <input type="hidden" name="action" value="save_gallery">
                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Judul / Nama Foto</label>
                    <input type="text" name="title" required placeholder="Judul dokumentasi foto..." class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none">
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Pilih File Foto atau Masukkan URL</label>
                    <div class="flex items-center gap-2 mb-2">
                      <button type="button" onclick="document.getElementById('fileUploadGalleryModal').click()" class="px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-2xs">
                        📁 Unggah dari Komputer
                      </button>
                      <input type="file" id="fileUploadGalleryModal" accept="image/*" class="hidden" onchange="handleModalGalleryUpload(this)">
                      <span id="galleryModalFileNotice" class="text-[11px] text-zinc-400 truncate">Atau tempel URL di bawah</span>
                    </div>
                    <input type="text" id="inputModalGalleryImageUrl" name="image_url" required placeholder="https://images.unsplash.com/... atau DataURL" class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none">
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Keterangan / Caption (Opsional)</label>
                    <input type="text" name="caption" placeholder="Keterangan foto..." class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none">
                  </div>
                  <div class="pt-3 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('modalAddGallery').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-zinc-400 hover:text-zinc-700">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full">Simpan ke Galeri</button>
                  </div>
                </form>
              </div>
            </div>

          <!-- TAB: VIDEO YOUTUBE -->
          <?php elseif ($tab === 'videos'): ?>
            <div class="bg-white rounded-3xl border border-zinc-200/80 shadow-xs p-6 sm:p-8 min-h-[480px]">
              <div class="flex items-center justify-between mb-6 pb-4 border-b border-zinc-100">
                <div>
                  <h2 class="font-extrabold text-lg text-zinc-900">Koleksi Video TV & YouTube (<?= count($videos) ?>)</h2>
                  <p class="text-xs text-zinc-500">Tayangan dokumenter, liputan positif, dan inovasi bangsa</p>
                </div>
                <button onclick="document.getElementById('modalAddVideo').classList.remove('hidden')" class="px-4 py-2 bg-[#CC0000] text-white text-xs font-bold rounded-full hover:bg-red-700 transition">
                  + Tambah Video
                </button>
              </div>

              <?php if (empty($videos)): ?>
                <div class="py-16 text-center text-zinc-400">
                  <p class="text-sm">Belum ada video yang ditambahkan.</p>
                </div>
              <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                  <?php foreach ($videos as $vid): ?>
                    <div class="border border-zinc-200 rounded-2xl overflow-hidden shadow-xs flex flex-col justify-between bg-white">
                      <div class="aspect-video w-full bg-black">
                        <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?= htmlspecialchars($vid['youtube_id']) ?>" title="<?= htmlspecialchars($vid['title']) ?>" frameborder="0" allowfullscreen></iframe>
                      </div>
                      <div class="p-4 flex flex-col justify-between flex-1">
                        <div>
                          <span class="text-[10px] font-bold text-[#CC0000] uppercase"><?= htmlspecialchars($vid['category']) ?></span>
                          <h4 class="font-bold text-xs text-zinc-900 line-clamp-2 mt-1"><?= htmlspecialchars($vid['title']) ?></h4>
                        </div>
                        <div class="mt-3 pt-3 border-t border-zinc-100 flex items-center justify-between text-[11px] text-zinc-400">
                          <span><?= htmlspecialchars($vid['date']) ?></span>
                          <a href="?delete_vid=<?= urlencode($vid['id']) ?>" onclick="return confirm('Hapus video ini?')" class="text-red-600 font-bold hover:underline">Hapus</a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Modal Add Video -->
            <div id="modalAddVideo" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
              <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-zinc-100">
                  <h3 class="font-bold text-base text-zinc-900">+ Tambah Video YouTube</h3>
                  <button onclick="document.getElementById('modalAddVideo').classList.add('hidden')" class="text-zinc-400 hover:text-zinc-700 text-lg">&times;</button>
                </div>
                <form method="POST" action="" class="space-y-3.5">
                  <input type="hidden" name="action" value="save_video">
                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Judul Video</label>
                    <input type="text" name="title" required placeholder="Judul liputan video..." class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none">
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">URL YouTube</label>
                    <input type="url" name="youtube_url" required placeholder="https://www.youtube.com/watch?v=..." class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none">
                  </div>
                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Kategori</label>
                    <select name="category" class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none bg-white font-bold">
                      <?php foreach ($categoriesList as $cat): ?>
                        <option value="<?= $cat ?>"><?= $cat ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="pt-3 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('modalAddVideo').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-zinc-400 hover:text-zinc-700">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full">Simpan Video</button>
                  </div>
                </form>
              </div>
            </div>

          <!-- TAB: PENGGUNA -->
          <?php elseif ($tab === 'users'): ?>
            <div class="bg-white rounded-3xl border border-zinc-200/80 shadow-xs p-6 sm:p-8 min-h-[480px]">
              <div class="flex items-center justify-between mb-6 pb-4 border-b border-zinc-100">
                <div>
                  <h2 class="font-extrabold text-lg text-zinc-900">Daftar Pengguna Studio (<?= count($users) ?>)</h2>
                  <p class="text-xs text-zinc-500">Anggota redaksi, wartawan, dan kontributor Good News Nusantara</p>
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-zinc-700">
                  <thead>
                    <tr class="border-b border-zinc-100 text-[11px] font-bold text-zinc-400 uppercase tracking-wider">
                      <th class="pb-3 px-2">Nama Pengguna</th>
                      <th class="pb-3 px-2">Username</th>
                      <th class="pb-3 px-2">Email</th>
                      <th class="pb-3 px-2">Peran (Role)</th>
                      <th class="pb-3 px-2">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($users as $u): ?>
                      <tr class="hover:bg-zinc-50/70 transition">
                        <td class="py-3 px-2 font-bold text-zinc-900 flex items-center gap-3">
                          <?php if (!empty($u['avatar'])): ?>
                            <img src="<?= htmlspecialchars($u['avatar']) ?>" class="w-8 h-8 rounded-full object-cover">
                          <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-zinc-100 text-zinc-500 font-bold flex items-center justify-center text-xs">
                              <?= strtoupper(substr($u['name'] ?: 'U', 0, 1)) ?>
                            </div>
                          <?php endif; ?>
                          <span><?= htmlspecialchars($u['name']) ?></span>
                        </td>
                        <td class="py-3 px-2 text-zinc-500">@<?= htmlspecialchars($u['username']) ?></td>
                        <td class="py-3 px-2 text-zinc-500"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="py-3 px-2">
                          <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                            <form method="POST" action="" class="inline-flex items-center gap-1.5">
                              <input type="hidden" name="action" value="update_user_role">
                              <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                              <select name="new_role" onchange="this.form.submit()" class="px-2 py-1 rounded-lg font-bold text-[10px] uppercase cursor-pointer border <?= $u['role'] === 'admin' ? 'bg-red-50 text-[#CC0000] border-red-200' : ($u['role'] === 'reviewer' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200') ?>">
                                <option value="sahabat" <?= ($u['role'] ?? '') === 'sahabat' ? 'selected' : '' ?>>SAHABAT</option>
                                <option value="reviewer" <?= ($u['role'] ?? '') === 'reviewer' ? 'selected' : '' ?>>REVIEWER</option>
                                <option value="admin" <?= ($u['role'] ?? '') === 'admin' ? 'selected' : '' ?>>ADMIN</option>
                              </select>
                            </form>
                          <?php else: ?>
                            <span class="px-2.5 py-1 rounded-lg font-bold text-[10px] uppercase border <?= $u['role'] === 'admin' ? 'bg-red-50 text-[#CC0000] border-red-200' : ($u['role'] === 'reviewer' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200') ?>">
                              <?= htmlspecialchars($u['role']) ?>
                            </span>
                          <?php endif; ?>
                        </td>
                        <td class="py-3 px-2">
                          <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full font-bold text-[10px] uppercase">
                            <?= htmlspecialchars($u['status'] ?? 'ACTIVE') ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

          <!-- TAB: PENGATURAN -->
          <?php elseif ($tab === 'settings'): ?>
            <div class="max-w-2xl bg-white rounded-3xl border border-zinc-200/80 shadow-xs p-6 sm:p-8">
              <h2 class="font-extrabold text-xl text-zinc-900 mb-6 pb-3 border-b border-zinc-100">
                Pengaturan Studio & Integrasi Google
              </h2>

              <form method="POST" action="" class="space-y-5">
                <input type="hidden" name="action" value="save_settings">

                <div>
                  <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5">Google OAuth Client ID</label>
                  <p class="text-[11px] text-zinc-500 mb-2">Digunakan untuk login otomatis menggunakan tombol "Masuk dengan Google" melalui Google Identity Services (GIS).</p>
                  <input type="text" name="google_client_id" value="<?= htmlspecialchars($googleClientId) ?>" placeholder="Contoh: xxxxx-xxxxx.apps.googleusercontent.com" class="w-full px-4 py-2.5 text-xs font-mono border border-zinc-200 rounded-2xl focus:border-[#CC0000] outline-none">
                </div>

                <div class="pt-4 border-t border-zinc-100 flex items-center justify-between">
                  <span class="text-xs text-zinc-400">Database: MySQL Ready (cPanel)</span>
                  <button type="submit" class="px-6 py-2.5 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full shadow-xs transition uppercase tracking-wider">
                    Simpan Pengaturan
                  </button>
                </div>
              </form>
            </div>
          <?php endif; ?>

        </main>
      </div>
    </div>

    <script>
      function toggleSidebar(forceState) {
        const sidebar = document.getElementById('sidebar');
        const userCard = document.getElementById('sidebarUserCard');
        const toggleIcon = document.getElementById('sidebarToggleIcon');
        if (!sidebar) return;

        const isCurrentlyCollapsed = sidebar.classList.contains('w-20');
        const shouldCollapse = forceState !== undefined ? forceState : !isCurrentlyCollapsed;

        if (shouldCollapse) {
          sidebar.classList.remove('w-64');
          sidebar.classList.add('w-20');
          sidebar.querySelectorAll('.sidebar-text').forEach(el => el.classList.add('hidden'));
          sidebar.querySelectorAll('nav a').forEach(el => el.classList.add('justify-center', 'px-2'));
          if (userCard) {
            userCard.classList.add('justify-center', 'p-1.5');
            userCard.classList.remove('p-2', 'sm:p-2.5');
          }
          if (toggleIcon) toggleIcon.style.transform = 'rotate(180deg)';
          localStorage.setItem('gnn_sidebar_collapsed', 'true');
        } else {
          sidebar.classList.remove('w-20');
          sidebar.classList.add('w-64');
          sidebar.querySelectorAll('.sidebar-text').forEach(el => el.classList.remove('hidden'));
          sidebar.querySelectorAll('nav a').forEach(el => el.classList.remove('justify-center', 'px-2'));
          if (userCard) {
            userCard.classList.remove('justify-center', 'p-1.5');
            userCard.classList.add('p-2', 'sm:p-2.5');
          }
          if (toggleIcon) toggleIcon.style.transform = 'rotate(0deg)';
          localStorage.setItem('gnn_sidebar_collapsed', 'false');
        }
      }

      // Restore collapsed state on load
      document.addEventListener('DOMContentLoaded', function() {
        if (localStorage.getItem('gnn_sidebar_collapsed') === 'true') {
          toggleSidebar(true);
        }
      });

      // Handle upload in modal add gallery
      function handleModalGalleryUpload(input) {
        if (input.files && input.files[0]) {
          const file = input.files[0];
          const reader = new FileReader();
          reader.onload = function(e) {
            const dataUrl = e.target.result;
            const inputUrl = document.getElementById('inputModalGalleryImageUrl');
            if (inputUrl) inputUrl.value = dataUrl;
            const notice = document.getElementById('galleryModalFileNotice');
            if (notice) notice.textContent = file.name;
          };
          reader.readAsDataURL(file);
        }
      }
    </script>
  <?php endif; ?>

</body>
</html>
