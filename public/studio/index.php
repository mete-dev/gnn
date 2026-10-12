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
            $userStatus = strtolower($user['status'] ?? 'active');
            if ($userStatus === 'blocked') {
                $err = "Akses akun Anda telah diblokir. Silakan hubungi Administrator.";
            } elseif ($userStatus === 'inactive') {
                $err = "Akun Anda sedang dinonaktifkan. Silakan hubungi Administrator.";
            } else {
                $userRole = $user['role'] ?? 'sahabat';
                if (strtolower($user['email']) === 'fadhilalanshor511@gmail.com' || strtolower($user['email']) === 'admin@gnn.my.id' || strtolower($user['username']) === 'admin') {
                    $userRole = 'admin';
                    try {
                        $db->prepare("UPDATE users SET role = 'admin', status = 'active' WHERE id = ?")->execute([$user['id']]);
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
            }
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
                $userStatus = strtolower($user['status'] ?? 'active');
                if ($userStatus === 'blocked') {
                    $err = "Akses akun Anda telah diblokir. Silakan hubungi Administrator.";
                } elseif ($userStatus === 'inactive') {
                    $err = "Akun Anda sedang dinonaktifkan. Silakan hubungi Administrator.";
                } else {
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
                }
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
$userRecord = null;
$isProfileIncomplete = false;

if ($currentUser) {
    if (strtolower($currentUser['email'] ?? '') === 'fadhilalanshor511@gmail.com' || strtolower($currentUser['email'] ?? '') === 'admin@gnn.my.id') {
        $currentUser['role'] = 'admin';
        $_SESSION['gnn_user']['role'] = 'admin';
    }

    try {
        $stmtCurrUsr = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmtCurrUsr->execute([$currentUser['id']]);
        $userRecord = $stmtCurrUsr->fetch();
        if ($userRecord) {
            $currentUserRole = strtolower($userRecord['role'] ?? 'sahabat');
            $currentUser['role'] = $currentUserRole;
            $_SESSION['gnn_user']['role'] = $currentUserRole;
            
            // Cek apakah akun belum melengkapi data pendaftaran (khususnya akun Google / non-admin)
            if ($currentUserRole !== 'admin') {
                if (empty($userRecord['whatsapp']) || empty($userRecord['address']) || empty($userRecord['city'])) {
                    $isProfileIncomplete = true;
                }
            }
        }
    } catch (Exception $e) {}
}

$currentUserRole = strtolower($currentUser['role'] ?? 'sahabat');

// Tab Routing & Hak Akses Tab
$tab = $_GET['tab'] ?? 'articles';
$filterStatus = $_GET['status'] ?? 'all';
$filterCategory = trim($_GET['category'] ?? 'all');
$searchQuery = trim($_GET['q'] ?? '');

// Batasi akses tab sesuai peran:
// - Sahabat: hanya boleh 'articles' & 'write'
// - Reviewer: hanya boleh 'articles' & 'write' (hanya akses penulisan & kurasi artikel)
// - Admin: full akses ke semua tab
$allowedTabs = ['articles', 'write'];
if ($currentUserRole === 'admin') {
    $allowedTabs = ['articles', 'write', 'gallery', 'videos', 'users', 'settings'];
}
if (!in_array($tab, $allowedTabs)) {
    $tab = 'articles';
}

// Actions requiring login
if ($currentUser) {
    // 1. Lengkapi Data Pendaftaran Akun Google / Penulis
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_google_profile') {
        $name = trim($_POST['name'] ?? ($currentUser['name'] ?? ''));
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Laki-laki');
        $birthDate = trim($_POST['birth_date'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (!$name || !$whatsapp || !$address || !$city) {
            $err = "Mohon lengkapi Nama, Nomor WhatsApp, Kota/Kabupaten, dan Alamat Domisili Anda.";
        } else {
            try {
                $stmtUpdProf = $db->prepare("UPDATE users SET name = ?, whatsapp = ?, gender = ?, birth_date = ?, province = ?, city = ?, address = ? WHERE id = ?");
                $stmtUpdProf->execute([$name, $whatsapp, $gender, $birthDate, $province, $city, $address, $currentUser['id']]);
                $_SESSION['gnn_user']['name'] = $name;
                $isProfileIncomplete = false;
                header("Location: /studio/?msg=" . urlencode("Data pendaftaran berhasil dilengkapi! Selamat berkarya di Good News Nusantara."));
                exit();
            } catch (Exception $e) {
                $err = "Gagal menyimpan data profil: " . $e->getMessage();
            }
        }
    }

    // 2. Delete Article
    if (isset($_GET['delete_art'])) {
        $delId = $_GET['delete_art'];
        if ($currentUserRole === 'admin') {
            $db->prepare("DELETE FROM articles WHERE id = ?")->execute([$delId]);
            header("Location: /studio/?tab=articles&msg=" . urlencode("Artikel berhasil dihapus."));
            exit();
        } elseif ($currentUserRole === 'sahabat') {
            // Sahabat hanya dapat menghapus draf karya sendiri
            $stmtChkDel = $db->prepare("SELECT id FROM articles WHERE id = ? AND (LOWER(author_email) = ? OR author = ?)");
            $stmtChkDel->execute([$delId, strtolower($currentUser['email']), $currentUser['name']]);
            if ($stmtChkDel->fetch()) {
                $db->prepare("DELETE FROM articles WHERE id = ?")->execute([$delId]);
                header("Location: /studio/?tab=articles&msg=" . urlencode("Artikel berhasil dihapus."));
                exit();
            } else {
                $err = "Anda hanya berhak menghapus artikel karya Anda sendiri.";
            }
        } elseif ($currentUserRole === 'reviewer') {
            // Reviewer dapat menghapus artikel karyanya atau karya sahabat
            $stmtChkDel = $db->prepare("SELECT id FROM articles WHERE id = ? AND (LOWER(author_email) = ? OR author = ? OR author_email IN (SELECT email FROM users WHERE role = 'sahabat') OR author_email IS NULL OR author_email = '')");
            $stmtChkDel->execute([$delId, strtolower($currentUser['email']), $currentUser['name']]);
            if ($stmtChkDel->fetch()) {
                $db->prepare("DELETE FROM articles WHERE id = ?")->execute([$delId]);
                header("Location: /studio/?tab=articles&msg=" . urlencode("Artikel berhasil dihapus."));
                exit();
            } else {
                $err = "Anda tidak berhak menghapus artikel ini.";
            }
        }
    }

    // Delete Video
    if (isset($_GET['delete_vid'])) {
        if ($currentUserRole === 'admin') {
            $delId = $_GET['delete_vid'];
            $db->prepare("DELETE FROM videos WHERE id = ?")->execute([$delId]);
            header("Location: /studio/?tab=videos&msg=" . urlencode("Video berhasil dihapus."));
            exit();
        } else {
            $err = "Akses ditolak.";
        }
    }

    // Delete Gallery
    if (isset($_GET['delete_gal'])) {
        if ($currentUserRole === 'admin') {
            $delId = $_GET['delete_gal'];
            $db->prepare("DELETE FROM gallery WHERE id = ?")->execute([$delId]);
            header("Location: /studio/?tab=gallery&msg=" . urlencode("Foto galeri berhasil dihapus."));
            exit();
        } else {
            $err = "Akses ditolak.";
        }
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
        $bulletsText = trim($_POST['bullets'] ?? '');
        $status = $_POST['status'] ?? 'publish';

        // Penegakan Aturan Hak Akses Berdasarkan Peran (Role):
        // 1. Sahabat: Hanya bisa menulis artikel dan menyimpannya sebagai draft
        if ($currentUserRole === 'sahabat') {
            $status = 'draft';
            $author = $currentUser['name'];
            $authorEmail = $currentUser['email'];
        } elseif ($currentUserRole === 'reviewer') {
            // 2. Reviewer: Bisa mempublikasi karyanya sendiri & mempublikasikan karya sahabat
            $author = trim($_POST['author'] ?? $currentUser['name']);
            $authorEmail = $currentUser['email'];
            if ($artId) {
                $stmtOrig = $db->prepare("SELECT author, author_email FROM articles WHERE id = ?");
                $stmtOrig->execute([$artId]);
                $origArt = $stmtOrig->fetch();
                if ($origArt) {
                    $author = $origArt['author'];
                    $authorEmail = $origArt['author_email'];
                }
            }
        } else {
            // 3. Admin: Full akses
            $author = trim($_POST['author'] ?? $currentUser['name']);
            $authorEmail = $currentUser['email'];
            if ($artId) {
                $stmtOrig = $db->prepare("SELECT author, author_email FROM articles WHERE id = ?");
                $stmtOrig->execute([$artId]);
                $origArt = $stmtOrig->fetch();
                if ($origArt && !empty($origArt['author_email'])) {
                    $authorEmail = $origArt['author_email'];
                }
            }
        }

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
                           ->execute([$autoGalId, $galTitle, $image, $caption, $author, $authorEmail, date('d M Y')]);
                    }
                } catch (Exception $e) {}
            }

            if ($artId) {
                // Verifikasi izin mengedit artikel
                $canUpdate = false;
                if ($currentUserRole === 'admin') {
                    $canUpdate = true;
                } elseif ($currentUserRole === 'sahabat') {
                    $stmtChkOwn = $db->prepare("SELECT id FROM articles WHERE id = ? AND (LOWER(author_email) = ? OR author = ?)");
                    $stmtChkOwn->execute([$artId, strtolower($currentUser['email']), $currentUser['name']]);
                    if ($stmtChkOwn->fetch()) $canUpdate = true;
                } elseif ($currentUserRole === 'reviewer') {
                    // Reviewer dapat mengedit karya sendiri atau karya sahabat
                    $stmtChkRev = $db->prepare("SELECT id FROM articles WHERE id = ? AND (LOWER(author_email) = ? OR author = ? OR author_email IN (SELECT email FROM users WHERE role = 'sahabat') OR author_email IS NULL OR author_email = '')");
                    $stmtChkRev->execute([$artId, strtolower($currentUser['email']), $currentUser['name']]);
                    if ($stmtChkRev->fetch()) $canUpdate = true;
                }

                if ($canUpdate) {
                    $editorName = ($currentUserRole === 'reviewer' || $currentUserRole === 'admin') ? $currentUser['name'] : 'Tim Redaksi GNN';
                    $stmt = $db->prepare("UPDATE articles SET title=?, category=?, sub_category=?, location=?, image=?, caption=?, image_credit=?, content=?, keywords=?, bullets=?, author=?, editor=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                    $stmt->execute([$title, $category, $subCategory, $location, $image, $caption, $imageCredit, $content, $keywords, $bulletsJson, $author, $editorName, $status, $artId]);
                    $msg = ($status === 'draft') ? "Artikel berhasil disimpan sebagai Draft!" : "Artikel berhasil diperbarui dan dipublikasikan!";
                } else {
                    $err = "Anda tidak berhak mengedit artikel ini.";
                }
            } else {
                // Insert artikel baru
                $newId = 'gnn-' . time();
                $editorName = ($currentUserRole === 'reviewer' || $currentUserRole === 'admin') ? $currentUser['name'] : 'Menunggu Reviewer';
                $stmt = $db->prepare("INSERT INTO articles (id, title, category, sub_category, location, image, caption, image_credit, content, keywords, bullets, author, author_email, editor, likes, views, status, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)");
                $stmt->execute([$newId, $title, $category, $subCategory, $location, $image, $caption, $imageCredit, $content, $keywords, $bulletsJson, $author, $authorEmail, $editorName, $status, date('d M Y')]);
                $msg = ($status === 'draft') ? "Artikel berhasil disimpan sebagai Draft untuk ditinjau oleh Reviewer!" : "Artikel baru berhasil diterbitkan!";
            }

            // Real-time Instant Indexing to Google jika diterbitkan
            if (strtolower($status) === 'publish' && empty($err)) {
                try {
                    $liveArticleUrl = 'https://gnn.web.id/' . slugify($title);
                    $indexRes = GoogleIndexer::notify($liveArticleUrl, 'URL_UPDATED');
                    if (!empty($indexRes['success'])) {
                        $msg .= " (Sinyal Google Indexing API berhasil dikirim!)";
                    }
                } catch (Exception $e) {}
            }

            if (empty($err)) {
                header("Location: /studio/?tab=articles&msg=" . urlencode($msg));
                exit();
            }
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

    // Backup Data Download (JSON Export)
    if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
        if (($currentUser['role'] ?? '') === 'admin') {
            try {
                $backupData = [
                    'site' => 'Good News Nusantara',
                    'exported_at' => date('Y-m-d H:i:s'),
                    'version' => '1.0',
                    'articles' => $db->query("SELECT * FROM articles")->fetchAll(PDO::FETCH_ASSOC),
                    'videos' => $db->query("SELECT * FROM videos")->fetchAll(PDO::FETCH_ASSOC),
                    'gallery' => $db->query("SELECT * FROM gallery")->fetchAll(PDO::FETCH_ASSOC),
                    'users' => $db->query("SELECT id, username, email, name, role, avatar, status, created_at FROM users")->fetchAll(PDO::FETCH_ASSOC),
                    'comments' => $db->query("SELECT * FROM comments")->fetchAll(PDO::FETCH_ASSOC),
                    'settings' => $db->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_ASSOC)
                ];

                $filename = 'gnn-backup-' . date('Ymd-His') . '.json';
                header('Content-Type: application/json; charset=UTF-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');
                echo json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit();
            } catch (Exception $e) {
                $err = "Gagal membuat berkas cadangan: " . $e->getMessage();
            }
        } else {
            $err = "Hanya Administrator yang berhak mengunduh cadangan data.";
        }
    }

    // Restore Data Upload (JSON Import)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_data') {
        if (($currentUser['role'] ?? '') === 'admin') {
            if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
                $uploadedContent = file_get_contents($_FILES['backup_file']['tmp_name']);
                $backup = json_decode($uploadedContent, true);

                if ($backup && is_array($backup) && (isset($backup['articles']) || isset($backup['site']))) {
                    try {
                        $mode = $_POST['restore_mode'] ?? 'merge'; // 'merge' or 'replace'
                        $importedCounts = [];

                        // 1. Articles
                        if (!empty($backup['articles']) && is_array($backup['articles'])) {
                            if ($mode === 'replace') {
                                $db->exec("DELETE FROM articles");
                            }
                            $stmtArt = $db->prepare("REPLACE INTO articles (id, category, sub_category, title, content, image, caption, breaking, featured, popular_rank, bullets, date, time_ago, author, author_email, editor, location, likes, views, keywords, image_credit, is_photo_gallery, photo_count, video_duration, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $count = 0;
                            foreach ($backup['articles'] as $row) {
                                if (empty($row['id']) || empty($row['title'])) continue;
                                $stmtArt->execute([
                                    $row['id'], $row['category'] ?? 'EKONOMI', $row['sub_category'] ?? '', $row['title'], $row['content'] ?? '',
                                    $row['image'] ?? '', $row['caption'] ?? '', $row['breaking'] ?? 0, $row['featured'] ?? 0, $row['popular_rank'] ?? 0,
                                    $row['bullets'] ?? '[]', $row['date'] ?? date('d M Y'), $row['time_ago'] ?? 'Baru saja', $row['author'] ?? 'Redaksi GNN',
                                    $row['author_email'] ?? '', $row['editor'] ?? 'Tim Redaksi GNN', $row['location'] ?? 'Good News Nusantara',
                                    $row['likes'] ?? 0, $row['views'] ?? 0, $row['keywords'] ?? '', $row['image_credit'] ?? '',
                                    $row['is_photo_gallery'] ?? 0, $row['photo_count'] ?? 0, $row['video_duration'] ?? '',
                                    $row['status'] ?? 'publish', $row['created_at'] ?? date('Y-m-d H:i:s'), $row['updated_at'] ?? date('Y-m-d H:i:s')
                                ]);
                                $count++;
                            }
                            $importedCounts[] = "$count artikel";
                        }

                        // 2. Videos
                        if (!empty($backup['videos']) && is_array($backup['videos'])) {
                            if ($mode === 'replace') {
                                $db->exec("DELETE FROM videos");
                            }
                            $stmtVid = $db->prepare("REPLACE INTO videos (id, title, youtube_url, youtube_id, description, category, sub_category, author, author_email, date, duration, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $count = 0;
                            foreach ($backup['videos'] as $row) {
                                if (empty($row['id']) || empty($row['title'])) continue;
                                $stmtVid->execute([
                                    $row['id'], $row['title'], $row['youtube_url'] ?? '', $row['youtube_id'] ?? '',
                                    $row['description'] ?? '', $row['category'] ?? 'EKONOMI', $row['sub_category'] ?? 'Ekonomi',
                                    $row['author'] ?? 'Tim Studio GNN', $row['author_email'] ?? '', $row['date'] ?? date('d M Y'),
                                    $row['duration'] ?? '03:45', $row['created_at'] ?? date('Y-m-d H:i:s')
                                ]);
                                $count++;
                            }
                            $importedCounts[] = "$count video";
                        }

                        // 3. Gallery
                        if (!empty($backup['gallery']) && is_array($backup['gallery'])) {
                            if ($mode === 'replace') {
                                $db->exec("DELETE FROM gallery");
                            }
                            $stmtGal = $db->prepare("REPLACE INTO gallery (id, title, image_url, file_size_kb, caption, author, author_email, date, used_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $count = 0;
                            foreach ($backup['gallery'] as $row) {
                                if (empty($row['id']) || empty($row['image_url'])) continue;
                                $stmtGal->execute([
                                    $row['id'], $row['title'] ?? 'Dokumentasi', $row['image_url'],
                                    $row['file_size_kb'] ?? 0, $row['caption'] ?? '', $row['author'] ?? 'Redaksi',
                                    $row['author_email'] ?? '', $row['date'] ?? date('d M Y'),
                                    $row['used_count'] ?? 0, $row['created_at'] ?? date('Y-m-d H:i:s')
                                ]);
                                $count++;
                            }
                            $importedCounts[] = "$count foto galeri";
                        }

                        // 4. Users
                        if (!empty($backup['users']) && is_array($backup['users'])) {
                            $stmtUsr = $db->prepare("INSERT INTO users (id, username, email, name, role, avatar, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role)");
                            $count = 0;
                            foreach ($backup['users'] as $row) {
                                if (empty($row['id']) || empty($row['email'])) continue;
                                $stmtUsr->execute([
                                    $row['id'], $row['username'] ?? explode('@', $row['email'])[0],
                                    $row['email'], $row['name'] ?? 'Penulis GNN', $row['role'] ?? 'sahabat',
                                    $row['avatar'] ?? '', $row['status'] ?? 'active', $row['created_at'] ?? date('Y-m-d H:i:s')
                                ]);
                                $count++;
                            }
                            $importedCounts[] = "$count pengguna";
                        }

                        $msg = "Pemulihan data (Restore) berhasil! Data diproses: " . implode(', ', $importedCounts) . ".";
                    } catch (Exception $e) {
                        $err = "Gagal memulihkan database: " . $e->getMessage();
                    }
                } else {
                    $err = "Berkas cadangan tidak valid atau format JSON salah.";
                }
            } else {
                $err = "Silakan pilih berkas cadangan (.json) yang ingin dipulihkan.";
            }
        } else {
            $err = "Hanya Administrator yang berhak memulihkan data.";
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

    // Update User Status: Aktif / Nonaktifkan / Blokir Akses (Admin only)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user_status') {
        if (($currentUser['role'] ?? '') === 'admin') {
            $targetUserId = trim($_POST['user_id'] ?? '');
            $newStatus = strtolower(trim($_POST['new_status'] ?? 'active'));
            if (in_array($newStatus, ['active', 'inactive', 'blocked']) && $targetUserId) {
                $stmtCheck = $db->prepare("SELECT email FROM users WHERE id = ?");
                $stmtCheck->execute([$targetUserId]);
                $targetUser = $stmtCheck->fetch();
                $targetEmail = strtolower($targetUser['email'] ?? '');

                if ($targetUserId === ($currentUser['id'] ?? '')) {
                    $err = "Anda tidak dapat mengubah status akun Anda sendiri.";
                } elseif ($targetEmail === 'fadhilalanshor511@gmail.com' || $targetEmail === 'admin@gnn.my.id') {
                    $err = "Akun Administrator Utama tidak dapat dinonaktifkan atau diblokir.";
                } else {
                    $stmtStatus = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                    $stmtStatus->execute([$newStatus, $targetUserId]);
                    $labelStatus = [
                        'active' => 'diaktifkan kembali',
                        'inactive' => 'dinonaktifkan',
                        'blocked' => 'diblokir aksesnya'
                    ][$newStatus] ?? 'diperbarui';
                    $msg = "Status pengguna berhasil " . $labelStatus . "!";
                }
            }
        } else {
            $err = "Akses ditolak. Hanya Administrator yang dapat mengubah status pengguna.";
        }
    }

    // Reset User Password (Admin only)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_user_password') {
        if (($currentUser['role'] ?? '') === 'admin') {
            $targetUserId = trim($_POST['user_id'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');

            if (!$targetUserId || strlen($newPassword) < 6) {
                $err = "Kata sandi baru minimal harus 6 karakter.";
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtReset = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtReset->execute([$hash, $targetUserId]);
                $msg = "Kata sandi pengguna berhasil direset!";
            }
        } else {
            $err = "Akses ditolak. Hanya Administrator yang dapat mereset kata sandi pengguna.";
        }
    }

    // Admin Update User Data (Admin only)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_update_user') {
        if (($currentUser['role'] ?? '') === 'admin') {
            $targetUserId = trim($_POST['user_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $username = strtolower(trim($_POST['username'] ?? ''));
            $email = strtolower(trim($_POST['email'] ?? ''));
            $role = trim($_POST['role'] ?? 'sahabat');
            $status = strtolower(trim($_POST['status'] ?? 'active'));
            $whatsapp = trim($_POST['whatsapp'] ?? '');

            if (!$targetUserId || !$name || !$username || !$email) {
                $err = "Nama, Username, dan Email wajib diisi.";
            } else {
                $stmtDup = $db->prepare("SELECT id FROM users WHERE (LOWER(email) = ? OR LOWER(username) = ?) AND id != ?");
                $stmtDup->execute([$email, $username, $targetUserId]);
                if ($stmtDup->fetch()) {
                    $err = "Email atau Username sudah digunakan pengguna lain.";
                } else {
                    if (($email === 'fadhilalanshor511@gmail.com' || $email === 'admin@gnn.my.id') && $role !== 'admin') {
                        $role = 'admin';
                    }
                    if (($email === 'fadhilalanshor511@gmail.com' || $email === 'admin@gnn.my.id') && $status !== 'active') {
                        $status = 'active';
                    }
                    $stmtUpd = $db->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ?, status = ?, whatsapp = ? WHERE id = ?");
                    $stmtUpd->execute([$name, $username, $email, $role, $status, $whatsapp, $targetUserId]);
                    $msg = "Data pengguna berhasil diperbarui!";
                }
            }
        } else {
            $err = "Akses ditolak. Hanya Administrator yang dapat mengubah data pengguna.";
        }
    }

    // Delete User (Administrator only)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        if ($currentUserRole === 'admin') {
            $targetUserId = trim($_POST['user_id'] ?? '');
            if (!$targetUserId) {
                $err = "ID Pengguna tidak valid.";
            } else {
                $stmtCheck = $db->prepare("SELECT email, username FROM users WHERE id = ?");
                $stmtCheck->execute([$targetUserId]);
                $targetUser = $stmtCheck->fetch();
                $targetEmail = strtolower($targetUser['email'] ?? '');
                $targetUsername = strtolower($targetUser['username'] ?? '');

                if ($targetUserId === ($currentUser['id'] ?? '')) {
                    $err = "Anda tidak dapat menghapus akun Anda sendiri.";
                } elseif ($targetEmail === 'fadhilalanshor511@gmail.com' || $targetEmail === 'admin@gnn.my.id' || $targetUsername === 'admin') {
                    $err = "Akun Administrator Utama tidak dapat dihapus.";
                } else {
                    $stmtDel = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmtDel->execute([$targetUserId]);
                    $msg = "Pengguna @" . htmlspecialchars($targetUser['username'] ?? '') . " berhasil dihapus secara permanen dari sistem.";
                }
            }
        } else {
            $err = "Akses ditolak. Hanya Administrator yang dapat menghapus pengguna.";
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
    // 1. Hitung total artikel & filter daftar sesuai Peran (Role)
    $artSql = "SELECT * FROM articles WHERE 1=1";
    $artParams = [];

    if ($currentUserRole === 'sahabat') {
        // Sahabat: hanya melihat artikel karyanya sendiri
        $artSql .= " AND (LOWER(author_email) = ? OR author = ?)";
        $artParams[] = strtolower($currentUser['email']);
        $artParams[] = $currentUser['name'];

        $stmtCnt = $db->prepare("SELECT COUNT(*) FROM articles WHERE LOWER(author_email) = ? OR author = ?");
        $stmtCnt->execute([strtolower($currentUser['email']), $currentUser['name']]);
        $totalArticlesCount = (int)$stmtCnt->fetchColumn();
    } elseif ($currentUserRole === 'reviewer') {
        // Reviewer: melihat artikel karyanya sendiri + artikel dari para Sahabat penulis
        $artSql .= " AND (LOWER(author_email) = ? OR author = ? OR author_email IN (SELECT email FROM users WHERE role = 'sahabat') OR author_email IS NULL OR author_email = '')";
        $artParams[] = strtolower($currentUser['email']);
        $artParams[] = $currentUser['name'];

        $stmtCnt = $db->prepare("SELECT COUNT(*) FROM articles WHERE LOWER(author_email) = ? OR author = ? OR author_email IN (SELECT email FROM users WHERE role = 'sahabat') OR author_email IS NULL OR author_email = ''");
        $stmtCnt->execute([strtolower($currentUser['email']), $currentUser['name']]);
        $totalArticlesCount = (int)$stmtCnt->fetchColumn();
    } else {
        // Administrator: Full akses melihat seluruh artikel
        $totalArticlesCount = (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
    }

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

    if ($currentUserRole === 'admin') {
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
    }

    if (isset($_GET['edit_art'])) {
        $stmtEdit = $db->prepare("SELECT * FROM articles WHERE id = ?");
        $stmtEdit->execute([$_GET['edit_art']]);
        $editArticle = $stmtEdit->fetch();
        if ($editArticle) {
            $canEdit = true;
            if ($currentUserRole === 'sahabat') {
                if (strtolower($editArticle['author_email']) !== strtolower($currentUser['email']) && $editArticle['author'] !== $currentUser['name']) {
                    $canEdit = false;
                }
            } elseif ($currentUserRole === 'reviewer') {
                $artAuthorEmail = strtolower($editArticle['author_email'] ?? '');
                $isOwn = ($artAuthorEmail === strtolower($currentUser['email']) || $editArticle['author'] === $currentUser['name']);
                if (!$isOwn) {
                    $stmtRoleChk = $db->prepare("SELECT role FROM users WHERE LOWER(email) = ?");
                    $stmtRoleChk->execute([$artAuthorEmail]);
                    $authorRole = $stmtRoleChk->fetchColumn();
                    if ($authorRole && $authorRole !== 'sahabat') {
                        $canEdit = false;
                    }
                }
            }

            if ($canEdit) {
                $tab = 'write';
                $editBullets = json_decode($editArticle['bullets'] ?: '[]', true);
                $editArticle['bullets_text'] = is_array($editBullets) ? implode("\n", $editBullets) : '';
            } else {
                $editArticle = null;
                $err = "Anda tidak memiliki hak untuk mengedit artikel ini.";
                $tab = 'articles';
            }
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

    <!-- MOBILE SIDEBAR BACKDROP OVERLAY -->
    <div id="mobileSidebarBackdrop" onclick="closeMobileSidebar()" class="fixed inset-0 bg-black/60 z-40 lg:hidden hidden backdrop-blur-xs transition-opacity duration-300"></div>

    <!-- LEFT SIDEBAR FRAME: Drawer on mobile, pinned sticky on lg screens -->
    <aside id="sidebar" class="fixed lg:sticky top-0 inset-y-0 left-0 -translate-x-full lg:translate-x-0 w-64 bg-white border-r border-zinc-200 h-screen flex flex-col justify-between shrink-0 transition-transform lg:transition-all duration-300 z-50 lg:z-40 overflow-hidden select-none shadow-2xl lg:shadow-none">
      <!-- Top Scrollable Container: Logo & Menus -->
      <div class="flex-1 overflow-y-auto overflow-x-hidden flex flex-col min-h-0">
        
        <!-- Sidebar Header: GNN Logo + Close / Collapse Button -->
        <div class="h-[64px] sm:h-[72px] px-3 sm:px-4 pt-0 pb-0 border-b border-zinc-100 flex items-center justify-between shrink-0">
          <a href="/" class="h-full flex items-center overflow-hidden group pt-0 pb-0 my-0">
            <img src="/icon/logoh.png" alt="Good News Nusantara" class="h-full w-auto max-h-[64px] sm:max-h-[72px] object-contain object-left sidebar-text block pt-0 pb-0 mt-0" style="height: 100%; max-height: 72px; margin-top: 0; padding-top: 0;" onerror="this.onerror=null; this.src='/logoh.png';">
            <div class="w-9 h-9 rounded-xl bg-[#CC0000] flex items-center justify-center shrink-0 shadow-xs hidden" id="sidebarMiniLogo">
              <span class="text-white font-black text-sm tracking-tighter">GNN</span>
            </div>
          </a>
          <!-- Close button on mobile / Collapse button on desktop -->
          <button onclick="closeMobileSidebar()" class="lg:hidden w-8 h-8 flex items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 hover:text-zinc-900 transition shrink-0 self-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
          <button onclick="toggleSidebar()" title="Kecilkan / Perbesar Menu" class="hidden lg:flex sidebar-text w-7 h-7 items-center justify-center rounded-lg text-zinc-400 hover:text-zinc-700 hover:bg-zinc-100 transition shrink-0 self-center">
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

            <?php if ($currentUserRole === 'admin'): ?>
            <!-- 2. Galeri Foto (Admin only) -->
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

            <!-- 3. Video YouTube (Admin only) -->
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

            <!-- 4. Pengguna (Admin only) -->
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

            <!-- 5. Pengaturan (Admin only) -->
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
            <?php endif; ?>
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
        <header class="h-[64px] sm:h-[72px] bg-white border-b border-zinc-200 px-4 sm:px-8 flex items-center justify-between sticky top-0 z-30">
          
          <!-- Left: Mobile Menu Trigger + Icon Badge & Title -->
          <div class="flex items-center gap-2 sm:gap-3 min-w-0">
            <!-- Mobile Hamburger Toggle -->
            <button type="button" onclick="openMobileSidebar()" class="lg:hidden w-9 h-9 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-700 flex items-center justify-center shrink-0 transition" title="Buka Menu Studio">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-zinc-100/90 border border-zinc-200/60 flex items-center justify-center text-[#CC0000] shrink-0 shadow-2xs">
              <?php if ($tab === 'gallery'): ?>
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              <?php elseif ($tab === 'videos'): ?>
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
              <?php elseif ($tab === 'users'): ?>
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
              <?php elseif ($tab === 'settings'): ?>
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
              <?php else: ?>
                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-[#CC0000]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              <?php endif; ?>
            </div>
            <h1 class="text-sm sm:text-lg font-extrabold text-zinc-900 tracking-tight truncate">
              <?php
                if ($tab === 'write') echo $editArticle ? 'Edit Artikel' : 'Tulis Artikel';
                elseif ($tab === 'gallery') echo 'Galeri Foto';
                elseif ($tab === 'videos') echo 'Video GNN TV';
                elseif ($tab === 'users') echo 'Kelola Tim';
                elseif ($tab === 'settings') echo 'Pengaturan';
                else echo 'Kelola Artikel';
              ?>
            </h1>
          </div>

          <!-- Right: Search, Filter, Action Button (Responsive for Mobile) -->
          <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
            
            <?php if ($tab === 'articles'): ?>
              <!-- Search Input Pill (Desktop & Tablet) -->
              <form method="GET" action="" class="relative hidden md:block">
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

              <!-- Category Dropdown Pill (Desktop) -->
              <div class="relative hidden sm:block">
                <select onchange="window.location.href='?tab=articles&q=<?= urlencode($searchQuery) ?>&status=<?= urlencode($filterStatus) ?>&category=' + encodeURIComponent(this.value)" class="appearance-none bg-white border border-zinc-200 rounded-full pl-3 pr-7 py-2 text-xs font-semibold text-zinc-700 shadow-xs outline-none cursor-pointer hover:border-zinc-300 transition max-w-[140px] truncate">
                  <option value="all" <?= $filterCategory === 'all' ? 'selected' : '' ?>>Semua Kategori</option>
                  <?php foreach ($categoriesList as $cat): ?>
                    <option value="<?= $cat ?>" <?= (strcasecmp($filterCategory, $cat) === 0) ? 'selected' : '' ?>><?= $cat ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-zinc-400">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
              </div>

              <!-- Status Dropdown Pill (Desktop) -->
              <div class="relative hidden sm:block">
                <select onchange="window.location.href='?tab=articles&q=<?= urlencode($searchQuery) ?>&category=<?= urlencode($filterCategory) ?>&status=' + this.value" class="appearance-none bg-white border border-zinc-200 rounded-full pl-3 pr-7 py-2 text-xs font-semibold text-zinc-700 shadow-xs outline-none cursor-pointer hover:border-zinc-300 transition">
                  <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua Status</option>
                  <option value="publish" <?= $filterStatus === 'publish' ? 'selected' : '' ?>>Publish</option>
                  <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-zinc-400">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
              </div>

              <!-- Action Button: + Buat Artikel -->
              <a href="?tab=write" class="bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full px-3.5 sm:px-5 py-2 sm:py-2.5 flex items-center gap-1 sm:gap-1.5 shadow-xs transition shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span class="inline">Tulis</span>
                <span class="hidden sm:inline">Artikel</span>
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
        <div class="px-3 sm:px-6 lg:px-8 pt-3 sm:pt-4">
          <?php if (!empty($msg) || !empty($_GET['msg'])): ?>
            <div class="bg-green-50 text-green-800 p-3 sm:p-3.5 rounded-2xl border border-green-200 text-xs font-bold flex items-center justify-between">
              <span><?= htmlspecialchars($msg ?: $_GET['msg']) ?></span>
              <button onclick="this.parentElement.remove()" class="text-green-600 font-black text-sm">&times;</button>
            </div>
          <?php endif; ?>
          <?php if (!empty($err)): ?>
            <div class="bg-red-50 text-red-800 p-3 sm:p-3.5 rounded-2xl border border-red-200 text-xs font-bold flex items-center justify-between">
              <span><?= htmlspecialchars($err) ?></span>
              <button onclick="this.parentElement.remove()" class="text-red-600 font-black text-sm">&times;</button>
            </div>
          <?php endif; ?>
        </div>

        <!-- MAIN BODY VIEW -->
        <main class="p-3 sm:p-6 lg:p-8 flex-1">

          <!-- TAB: ARTICLES -->
          <?php if ($tab === 'articles'): ?>
            <div class="bg-white rounded-2xl sm:rounded-3xl border border-zinc-200/80 shadow-xs p-3.5 sm:p-6 lg:p-8 min-h-[480px] flex flex-col justify-between">
              
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
            <div class="max-w-5xl mx-auto bg-white rounded-2xl sm:rounded-3xl border border-zinc-200/80 shadow-xs p-4 sm:p-6 lg:p-8">
              
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
                    <input type="text" name="author" value="<?= htmlspecialchars($editArticle['author'] ?? $currentUser['name']) ?>" <?= ($currentUserRole === 'sahabat') ? 'readonly' : '' ?> class="w-full px-4 py-2.5 text-xs font-bold border border-zinc-200 rounded-2xl focus:border-[#CC0000] outline-none <?= ($currentUserRole === 'sahabat') ? 'bg-zinc-100 text-zinc-600' : 'bg-zinc-50' ?>">
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-zinc-800 uppercase tracking-wider mb-1.5">Status Penerbitan</label>
                    <?php if ($currentUserRole === 'sahabat'): ?>
                      <input type="hidden" name="status" value="draft">
                      <div class="px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-2xl text-xs font-bold text-amber-800 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>Draft (Akan ditinjau oleh Reviewer / Redaksi)</span>
                      </div>
                    <?php else: ?>
                      <select name="status" class="w-full px-4 py-2.5 text-xs font-bold border border-zinc-200 rounded-2xl focus:border-[#CC0000] outline-none bg-white cursor-pointer">
                        <option value="publish" <?= ($editArticle['status'] ?? 'publish') === 'publish' ? 'selected' : '' ?>>Terbitkan (Publish)</option>
                        <option value="draft" <?= ($editArticle['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Simpan Sebagai Draf (Draft)</option>
                      </select>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- User Notice Box -->
                <div class="p-3.5 rounded-2xl bg-red-50/70 border border-red-100 flex items-center justify-between text-xs">
                  <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-zinc-600">
                      Anda masuk sebagai <strong><?= htmlspecialchars($currentUser['name']) ?></strong> (<?= strtoupper($currentUserRole) ?>)
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
                    <?php if ($currentUserRole === 'sahabat'): ?>
                      <button type="submit" class="px-7 py-3 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full shadow-sm transition uppercase tracking-wider">
                        Simpan sebagai Draft
                      </button>
                    <?php else: ?>
                      <button type="button" onclick="const sel=document.querySelector('select[name=status]'); if(sel) sel.value='draft'; document.getElementById('articleWriteForm').submit();" class="px-5 py-2.5 border border-zinc-200 text-zinc-700 hover:bg-zinc-50 font-bold text-xs rounded-full shadow-2xs transition">
                        Simpan Draf
                      </button>
                      <button type="submit" class="px-7 py-3 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full shadow-sm transition uppercase tracking-wider">
                        <?= ($editArticle && ($editArticle['status'] ?? '') === 'publish') ? 'Perbarui & Terbitkan' : 'Terbitkan Sekarang' ?>
                      </button>
                    <?php endif; ?>
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
                <table class="w-full text-left text-xs text-zinc-700 whitespace-nowrap">
                  <thead>
                    <tr class="border-b border-zinc-100 text-[11px] font-bold text-zinc-400 uppercase tracking-wider">
                      <th class="pb-3 px-3">Nama Pengguna</th>
                      <th class="pb-3 px-3">Username</th>
                      <th class="pb-3 px-3">Email</th>
                      <th class="pb-3 px-3">Peran (Role)</th>
                      <th class="pb-3 px-3">Status</th>
                      <th class="pb-3 px-3 text-right">Aksi Manajemen</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($users as $u): ?>
                      <?php
                        $uStatus = strtolower($u['status'] ?? 'active');
                        $isSuperAdmin = in_array(strtolower($u['email'] ?? ''), ['fadhilalanshor511@gmail.com', 'admin@gnn.my.id']);
                        $isSelf = ($u['id'] === ($currentUser['id'] ?? ''));
                        $userSafeJson = htmlspecialchars(json_encode([
                          'id' => $u['id'],
                          'name' => $u['name'],
                          'username' => $u['username'],
                          'email' => $u['email'],
                          'role' => $u['role'] ?? 'sahabat',
                          'status' => $uStatus,
                          'avatar' => $u['avatar'] ?? '',
                          'whatsapp' => $u['whatsapp'] ?? '',
                          'gender' => $u['gender'] ?? '',
                          'birth_date' => $u['birth_date'] ?? '',
                          'country' => $u['country'] ?? '',
                          'province' => $u['province'] ?? '',
                          'city' => $u['city'] ?? '',
                          'address' => $u['address'] ?? '',
                          'created_at' => $u['created_at'] ?? ''
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                      ?>
                      <tr class="hover:bg-zinc-50/70 transition">
                        <td class="py-3.5 px-3 font-bold text-zinc-900">
                          <div class="flex items-center gap-3">
                            <?php if (!empty($u['avatar'])): ?>
                              <img src="<?= htmlspecialchars($u['avatar']) ?>" class="w-8 h-8 rounded-full object-cover border border-zinc-200">
                            <?php else: ?>
                              <div class="w-8 h-8 rounded-full bg-zinc-100 text-zinc-600 font-bold flex items-center justify-center text-xs border border-zinc-200">
                                <?= strtoupper(substr($u['name'] ?: 'U', 0, 1)) ?>
                              </div>
                            <?php endif; ?>
                            <div>
                              <div><?= htmlspecialchars($u['name']) ?></div>
                              <?php if ($isSuperAdmin): ?>
                                <span class="text-[9px] font-bold text-red-600 bg-red-50 px-1.5 py-0.5 rounded">Super Admin</span>
                              <?php elseif ($isSelf): ?>
                                <span class="text-[9px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded">Akun Anda</span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </td>
                        <td class="py-3.5 px-3 text-zinc-500 font-mono text-[11px]">@<?= htmlspecialchars($u['username']) ?></td>
                        <td class="py-3.5 px-3 text-zinc-500"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="py-3.5 px-3">
                          <span class="px-2.5 py-1 rounded-lg font-bold text-[10px] uppercase border <?= $u['role'] === 'admin' ? 'bg-red-50 text-[#CC0000] border-red-200' : ($u['role'] === 'reviewer' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200') ?>">
                            <?= htmlspecialchars($u['role']) ?>
                          </span>
                        </td>
                        <td class="py-3.5 px-3">
                          <?php if ($uStatus === 'active'): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full font-bold text-[10px] uppercase">
                              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
                            </span>
                          <?php elseif ($uStatus === 'inactive'): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full font-bold text-[10px] uppercase">
                              <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Nonaktif
                            </span>
                          <?php elseif ($uStatus === 'blocked'): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-full font-bold text-[10px] uppercase">
                              <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Diblokir
                            </span>
                          <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-zinc-50 text-zinc-700 border border-zinc-200 rounded-full font-bold text-[10px] uppercase">
                              <?= htmlspecialchars($uStatus) ?>
                            </span>
                          <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-3 text-right">
                          <div class="inline-flex items-center gap-1.5 justify-end">
                            <!-- Tombol Lihat Info -->
                            <button type="button" onclick='openModalUserInfo(<?= $userSafeJson ?>)' class="px-2 py-1 text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100 rounded-lg border border-zinc-200 transition inline-flex items-center gap-1 text-[11px] font-bold" title="Lihat Info Lengkap">
                              <svg class="w-3.5 h-3.5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                              <span>Info</span>
                            </button>

                            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                              <!-- Tombol Edit Data -->
                              <button type="button" onclick='openModalUserEdit(<?= $userSafeJson ?>)' class="px-2 py-1 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg border border-blue-200 transition inline-flex items-center gap-1 text-[11px] font-bold" title="Edit Data Pengguna">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <span>Edit</span>
                              </button>

                              <!-- Tombol Reset Sandi -->
                              <button type="button" onclick='openModalUserReset(<?= $userSafeJson ?>)' class="px-2 py-1 text-amber-700 hover:text-amber-900 hover:bg-amber-50 rounded-lg border border-amber-200 transition inline-flex items-center gap-1 text-[11px] font-bold" title="Reset Kata Sandi">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                <span>Sandi</span>
                              </button>

                              <!-- Status Selector: Aktif / Nonaktifkan / Blokir -->
                              <?php if (!$isSuperAdmin && !$isSelf): ?>
                                <form method="POST" action="" class="inline-block">
                                  <input type="hidden" name="action" value="update_user_status">
                                  <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                                  <select name="new_status" onchange="if(confirm('Ubah status akun @<?= htmlspecialchars($u['username']) ?> menjadi ' + this.options[this.selectedIndex].text + '?')) this.form.submit(); else this.value='<?= $uStatus ?>';" class="px-2 py-1 text-[11px] font-bold rounded-lg border cursor-pointer outline-none transition <?= $uStatus === 'active' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : ($uStatus === 'inactive' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-rose-50 text-rose-800 border-rose-200') ?>">
                                    <option value="active" <?= $uStatus === 'active' ? 'selected' : '' ?>>🟢 Aktif</option>
                                    <option value="inactive" <?= $uStatus === 'inactive' ? 'selected' : '' ?>>🟡 Nonaktifkan</option>
                                    <option value="blocked" <?= $uStatus === 'blocked' ? 'selected' : '' ?>>🔴 Blokir Akses</option>
                                  </select>
                                </form>

                                <!-- Tombol Hapus Pengguna Permanen (Admin only) -->
                                <form method="POST" action="" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengguna @<?= htmlspecialchars($u['username']) ?> secara permanen? Data yang telah dihapus tidak dapat dipulihkan.');">
                                  <input type="hidden" name="action" value="delete_user">
                                  <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                                  <button type="submit" class="px-2 py-1 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg border border-red-200 transition inline-flex items-center gap-1 text-[11px] font-bold cursor-pointer" title="Hapus Pengguna Secara Permanen">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    <span>Hapus</span>
                                  </button>
                                </form>
                              <?php endif; ?>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- MODAL: LIHAT INFO PENGGUNA -->
            <div id="modalUserInfo" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
              <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-100">
                  <h3 class="font-extrabold text-base text-zinc-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>Informasi Lengkap Pengguna</span>
                  </h3>
                  <button type="button" onclick="document.getElementById('modalUserInfo').classList.add('hidden')" class="w-8 h-8 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-500 flex items-center justify-center font-bold text-lg">&times;</button>
                </div>

                <div class="space-y-4">
                  <!-- Profil Singkat Header -->
                  <div class="flex items-center gap-4 p-4 bg-zinc-50 rounded-2xl border border-zinc-100">
                    <img id="infoAvatar" src="" class="w-14 h-14 rounded-full object-cover border-2 border-white shadow-xs hidden">
                    <div id="infoAvatarFallback" class="w-14 h-14 rounded-full bg-blue-100 text-blue-700 font-extrabold text-xl flex items-center justify-center border-2 border-white shadow-xs">U</div>
                    <div>
                      <h4 id="infoName" class="font-extrabold text-base text-zinc-900">-</h4>
                      <p id="infoUsername" class="text-xs text-zinc-500 font-mono">-</p>
                      <div class="flex items-center gap-2 mt-1.5">
                        <span id="infoRole" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-200">-</span>
                        <span id="infoStatus" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">-</span>
                      </div>
                    </div>
                  </div>

                  <!-- Grid Informasi Detail -->
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 bg-zinc-50/70 rounded-xl border border-zinc-100">
                      <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Email</span>
                      <span id="infoEmail" class="font-semibold text-zinc-900 break-all">-</span>
                    </div>
                    <div class="p-3 bg-zinc-50/70 rounded-xl border border-zinc-100">
                      <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">WhatsApp / No. HP</span>
                      <span id="infoWhatsapp" class="font-semibold text-zinc-900">-</span>
                    </div>
                    <div class="p-3 bg-zinc-50/70 rounded-xl border border-zinc-100">
                      <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Jenis Kelamin</span>
                      <span id="infoGender" class="font-semibold text-zinc-900">-</span>
                    </div>
                    <div class="p-3 bg-zinc-50/70 rounded-xl border border-zinc-100">
                      <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Tanggal Lahir</span>
                      <span id="infoBirthDate" class="font-semibold text-zinc-900">-</span>
                    </div>
                    <div class="p-3 bg-zinc-50/70 rounded-xl border border-zinc-100 sm:col-span-2">
                      <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Daerah / Lokasi</span>
                      <span id="infoLocation" class="font-semibold text-zinc-900">-</span>
                    </div>
                    <div class="p-3 bg-zinc-50/70 rounded-xl border border-zinc-100 sm:col-span-2">
                      <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Alamat Domisili</span>
                      <span id="infoAddress" class="font-semibold text-zinc-900">-</span>
                    </div>
                    <div class="p-3 bg-zinc-50/70 rounded-xl border border-zinc-100 sm:col-span-2">
                      <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider block">Waktu Terdaftar</span>
                      <span id="infoCreatedAt" class="font-semibold text-zinc-900">-</span>
                    </div>
                  </div>

                  <div class="pt-3 flex justify-end">
                    <button type="button" onclick="document.getElementById('modalUserInfo').classList.add('hidden')" class="px-5 py-2 bg-zinc-800 hover:bg-zinc-900 text-white font-bold text-xs rounded-full transition">
                      Tutup
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- MODAL: EDIT DATA PENGGUNA -->
            <div id="modalUserEdit" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
              <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-100">
                  <h3 class="font-extrabold text-base text-zinc-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Edit Data Pengguna</span>
                  </h3>
                  <button type="button" onclick="document.getElementById('modalUserEdit').classList.add('hidden')" class="w-8 h-8 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-500 flex items-center justify-center font-bold text-lg">&times;</button>
                </div>

                <form method="POST" action="" class="space-y-3.5">
                  <input type="hidden" name="action" value="admin_update_user">
                  <input type="hidden" name="user_id" id="editUserId">

                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Nama Lengkap *</label>
                    <input type="text" name="name" id="editUserName" required class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-blue-600 outline-none">
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Username *</label>
                    <input type="text" name="username" id="editUserUsername" required class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-blue-600 outline-none font-mono">
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Email *</label>
                    <input type="email" name="email" id="editUserEmail" required class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-blue-600 outline-none">
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">No. WhatsApp</label>
                    <input type="text" name="whatsapp" id="editUserWhatsapp" placeholder="08xxxxxxxxxx" class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-blue-600 outline-none">
                  </div>

                  <div class="grid grid-cols-2 gap-3">
                    <div>
                      <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Peran (Role)</label>
                      <select name="role" id="editUserRole" class="w-full px-3 py-2 text-xs border border-zinc-200 rounded-xl focus:border-blue-600 outline-none font-bold bg-white cursor-pointer">
                        <option value="sahabat">SAHABAT</option>
                        <option value="reviewer">REVIEWER</option>
                        <option value="admin">ADMIN</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Status Akun</label>
                      <select name="status" id="editUserStatus" class="w-full px-3 py-2 text-xs border border-zinc-200 rounded-xl focus:border-blue-600 outline-none font-bold bg-white cursor-pointer">
                        <option value="active">🟢 AKTIF</option>
                        <option value="inactive">🟡 NONAKTIF</option>
                        <option value="blocked">🔴 DIBLOKIR</option>
                      </select>
                    </div>
                  </div>

                  <div class="pt-4 flex justify-end gap-2 border-t border-zinc-100">
                    <button type="button" onclick="document.getElementById('modalUserEdit').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-zinc-400 hover:text-zinc-700">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-full shadow-xs transition">
                      Simpan Perubahan
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <!-- MODAL: RESET KATA SANDI -->
            <div id="modalUserResetPassword" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden">
              <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-zinc-100">
                  <h3 class="font-extrabold text-base text-zinc-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    <span>Reset Kata Sandi</span>
                  </h3>
                  <button type="button" onclick="document.getElementById('modalUserResetPassword').classList.add('hidden')" class="w-8 h-8 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-500 flex items-center justify-center font-bold text-lg">&times;</button>
                </div>

                <form method="POST" action="" class="space-y-4">
                  <input type="hidden" name="action" value="reset_user_password">
                  <input type="hidden" name="user_id" id="resetUserId">

                  <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900">
                    Mereset kata sandi untuk akun:
                    <strong id="resetUserDisplayName" class="block font-bold mt-0.5 text-zinc-900">-</strong>
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Kata Sandi Baru *</label>
                    <input type="password" name="new_password" id="resetNewPassword" required minlength="6" placeholder="Minimal 6 karakter..." class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-amber-600 outline-none">
                    <p class="text-[11px] text-zinc-400 mt-1">Pengguna dapat langsung menggunakan sandi baru ini untuk login.</p>
                  </div>

                  <div class="pt-2 flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('modalUserResetPassword').classList.add('hidden')" class="px-4 py-2 text-xs font-bold text-zinc-400 hover:text-zinc-700">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-full shadow-xs transition">
                      Reset Sandi Sekarang
                    </button>
                  </div>
                </form>
              </div>
            </div>

          <!-- TAB: PENGATURAN & BACKUP/RESTORE -->
          <?php elseif ($tab === 'settings'): ?>
            <div class="max-w-4xl space-y-6">
              
              <!-- SECTION 1: CADANGAN DATA (BACKUP) -->
              <div class="bg-white rounded-2xl sm:rounded-3xl border border-zinc-200/80 shadow-xs p-5 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 mb-5 border-b border-zinc-100">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </div>
                    <div>
                      <h2 class="font-extrabold text-lg text-zinc-900 tracking-tight">Cadangkan Data (Backup)</h2>
                      <p class="text-xs text-zinc-500">Ekspor seluruh artikel, berita, galeri foto, video, dan pengguna ke berkas aman JSON.</p>
                    </div>
                  </div>
                  <a href="?action=download_backup" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-full shadow-xs transition flex items-center justify-center gap-2 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Unduh Cadangan (.JSON)</span>
                  </a>
                </div>

                <!-- Backup Stats Summary -->
                <?php
                  $cntArticles = (int)$db->query("SELECT COUNT(*) FROM articles")->fetchColumn();
                  $cntGallery = (int)$db->query("SELECT COUNT(*) FROM gallery")->fetchColumn();
                  $cntVideos = (int)$db->query("SELECT COUNT(*) FROM videos")->fetchColumn();
                  $cntUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
                ?>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                  <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <div class="text-xs text-zinc-400 font-bold uppercase">Artikel</div>
                    <div class="text-base font-extrabold text-zinc-900 mt-0.5"><?= number_format($cntArticles) ?></div>
                  </div>
                  <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <div class="text-xs text-zinc-400 font-bold uppercase">Galeri Foto</div>
                    <div class="text-base font-extrabold text-zinc-900 mt-0.5"><?= number_format($cntGallery) ?></div>
                  </div>
                  <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <div class="text-xs text-zinc-400 font-bold uppercase">Video</div>
                    <div class="text-base font-extrabold text-zinc-900 mt-0.5"><?= number_format($cntVideos) ?></div>
                  </div>
                  <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-100">
                    <div class="text-xs text-zinc-400 font-bold uppercase">Pengguna</div>
                    <div class="text-base font-extrabold text-zinc-900 mt-0.5"><?= number_format($cntUsers) ?></div>
                  </div>
                </div>
              </div>

              <!-- SECTION 2: PEMULIHAN DATA (RESTORE) -->
              <div class="bg-white rounded-2xl sm:rounded-3xl border border-zinc-200/80 shadow-xs p-5 sm:p-8">
                <div class="flex items-center gap-3 pb-4 mb-5 border-b border-zinc-100">
                  <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 border border-blue-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                  </div>
                  <div>
                    <h2 class="font-extrabold text-lg text-zinc-900 tracking-tight">Pulihkan Data (Restore)</h2>
                    <p class="text-xs text-zinc-500">Unggah berkas cadangan JSON yang pernah Anda unduh sebelumnya untuk memulihkan database.</p>
                  </div>
                </div>

                <form method="POST" action="" enctype="multipart/form-data" class="space-y-4" onsubmit="return confirm('Apakah Anda yakin ingin memulihkan data? Pastikan file backup berasal dari sumber yang terpercaya.');">
                  <input type="hidden" name="action" value="restore_data">

                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5">Pilih Berkas Cadangan (.JSON) *</label>
                    <input type="file" name="backup_file" accept=".json,application/json" required class="w-full px-3.5 py-2.5 text-xs bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:border-blue-600 outline-none file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                  </div>

                  <div>
                    <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wider mb-1.5">Metode Pemulihan</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <label class="p-3 border border-zinc-200 rounded-xl flex items-start gap-2.5 cursor-pointer hover:border-blue-300 transition">
                        <input type="radio" name="restore_mode" value="merge" checked class="mt-0.5 text-blue-600">
                        <div class="text-xs">
                          <span class="font-bold text-zinc-900 block">Gabungkan (Merge & Update)</span>
                          <span class="text-zinc-500 text-[11px]">Memperbarui data yang cocok dan menambahkan data baru tanpa menghapus data lain yang ada. (Aman)</span>
                        </div>
                      </label>
                      <label class="p-3 border border-red-200 bg-red-50/30 rounded-xl flex items-start gap-2.5 cursor-pointer hover:border-red-300 transition">
                        <input type="radio" name="restore_mode" value="replace" class="mt-0.5 text-red-600">
                        <div class="text-xs">
                          <span class="font-bold text-red-700 block">Ganti Total (Replace All)</span>
                          <span class="text-zinc-500 text-[11px]">Mengosongkan database saat ini dan menggantinya seluruhnya dengan data cadangan. (Hati-hati)</span>
                        </div>
                      </label>
                    </div>
                  </div>

                  <div class="pt-3 flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-full shadow-xs transition uppercase tracking-wider">
                      Mulai Pemulihan Data
                    </button>
                  </div>
                </form>
              </div>

            </div>
          <?php endif; ?>

        </main>
      </div>
    </div>

    <?php if (!empty($isProfileIncomplete)): ?>
    <!-- MODAL WAJIB LENGKAPI DATA PENDAFTARAN -->
    <div id="modalCompleteProfile" class="fixed inset-0 bg-black/80 z-[100] flex items-center justify-center p-4 backdrop-blur-sm overflow-y-auto">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl relative my-auto">
        <div class="text-center pb-4 mb-4 border-b border-zinc-100">
          <div class="w-14 h-14 bg-red-50 text-[#CC0000] rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-2xs border border-red-100">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
          </div>
          <h3 class="font-extrabold text-lg text-zinc-900 tracking-tight">Lengkapi Data Pendaftaran</h3>
          <p class="text-xs text-zinc-500 mt-1 leading-relaxed">Akun Google Anda berhasil terhubung. Mohon lengkapi data profil &amp; identitas kepenulisan Anda sebelum mulai berkarya di Good News Nusantara.</p>
        </div>

        <form method="POST" action="" class="space-y-3.5">
          <input type="hidden" name="action" value="complete_google_profile">

          <div>
            <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Nama Lengkap Penulis *</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($userRecord['name'] ?? $currentUser['name']) ?>" class="w-full px-3.5 py-2.5 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none font-bold">
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Username</label>
              <input type="text" value="<?= htmlspecialchars($userRecord['username'] ?? $currentUser['username']) ?>" readonly class="w-full px-3.5 py-2.5 text-xs border border-zinc-200 rounded-xl bg-zinc-100 text-zinc-500 outline-none font-mono">
            </div>
            <div>
              <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Email</label>
              <input type="email" value="<?= htmlspecialchars($userRecord['email'] ?? $currentUser['email']) ?>" readonly class="w-full px-3.5 py-2.5 text-xs border border-zinc-200 rounded-xl bg-zinc-100 text-zinc-500 outline-none truncate">
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Nomor WhatsApp Aktif *</label>
            <input type="tel" name="whatsapp" required value="<?= htmlspecialchars($userRecord['whatsapp'] ?? '') ?>" placeholder="08xxxxxxxxxx" class="w-full px-3.5 py-2.5 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none">
            <p class="text-[10px] text-zinc-400 mt-0.5">Digunakan tim redaksi untuk konfirmasi penerbitan dan informasi karya.</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Jenis Kelamin *</label>
              <select name="gender" class="w-full px-3 py-2.5 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none bg-white cursor-pointer font-bold">
                <option value="Laki-laki" <?= ($userRecord['gender'] ?? '') === 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="Perempuan" <?= ($userRecord['gender'] ?? '') === 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Tanggal Lahir *</label>
              <input type="date" name="birth_date" required value="<?= htmlspecialchars($userRecord['birth_date'] ?? '') ?>" class="w-full px-3 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Provinsi *</label>
              <input type="text" name="province" required value="<?= htmlspecialchars($userRecord['province'] ?? '') ?>" placeholder="Contoh: Jawa Timur" class="w-full px-3.5 py-2.5 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none">
            </div>
            <div>
              <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Kota / Kabupaten *</label>
              <input type="text" name="city" required value="<?= htmlspecialchars($userRecord['city'] ?? '') ?>" placeholder="Contoh: Surabaya" class="w-full px-3.5 py-2.5 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none">
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-zinc-700 uppercase mb-1">Alamat Lengkap Domisili *</label>
            <textarea name="address" required rows="2" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan..." class="w-full px-3.5 py-2 text-xs border border-zinc-200 rounded-xl focus:border-[#CC0000] outline-none"><?= htmlspecialchars($userRecord['address'] ?? '') ?></textarea>
          </div>

          <div class="pt-3">
            <button type="submit" class="w-full py-3 bg-[#CC0000] hover:bg-red-700 text-white font-bold text-xs rounded-full shadow-md transition uppercase tracking-wider cursor-pointer">
              Simpan &amp; Lanjutkan ke Studio
            </button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <script>
      function openMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('mobileSidebarBackdrop');
        if (sidebar) {
          sidebar.classList.remove('-translate-x-full');
          sidebar.classList.add('translate-x-0');
        }
        if (backdrop) {
          backdrop.classList.remove('hidden');
        }
      }

      function closeMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('mobileSidebarBackdrop');
        if (sidebar) {
          sidebar.classList.add('-translate-x-full');
          sidebar.classList.remove('translate-x-0');
        }
        if (backdrop) {
          backdrop.classList.add('hidden');
        }
      }

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
          const miniLogo = document.getElementById('sidebarMiniLogo');
          if (miniLogo) miniLogo.classList.remove('hidden');
          if (toggleIcon) toggleIcon.style.transform = 'rotate(180deg)';
          localStorage.setItem('gnn_sidebar_collapsed', 'true');
        } else {
          sidebar.classList.remove('w-20');
          sidebar.classList.add('w-64');
          sidebar.querySelectorAll('.sidebar-text').forEach(el => el.classList.remove('hidden'));
          sidebar.querySelectorAll('nav a').forEach(el => el.classList.remove('justify-center', 'px-2'));
          const miniLogo = document.getElementById('sidebarMiniLogo');
          if (miniLogo) miniLogo.classList.add('hidden');
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

      // User Management Modals
      function openModalUserInfo(user) {
        if (!user) return;
        const imgAvatar = document.getElementById('infoAvatar');
        const fallbackAvatar = document.getElementById('infoAvatarFallback');
        if (user.avatar) {
          imgAvatar.src = user.avatar;
          imgAvatar.classList.remove('hidden');
          fallbackAvatar.classList.add('hidden');
        } else {
          fallbackAvatar.textContent = (user.name || 'U').charAt(0).toUpperCase();
          fallbackAvatar.classList.remove('hidden');
          imgAvatar.classList.add('hidden');
        }

        document.getElementById('infoName').textContent = user.name || '-';
        document.getElementById('infoUsername').textContent = '@' + (user.username || '-');
        document.getElementById('infoEmail').textContent = user.email || '-';
        document.getElementById('infoRole').textContent = (user.role || '-').toUpperCase();
        
        const statusEl = document.getElementById('infoStatus');
        const st = (user.status || 'active').toLowerCase();
        if (st === 'active') {
          statusEl.textContent = 'AKTIF';
          statusEl.className = 'px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200';
        } else if (st === 'inactive') {
          statusEl.textContent = 'NONAKTIF';
          statusEl.className = 'px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-50 text-amber-700 border border-amber-200';
        } else if (st === 'blocked') {
          statusEl.textContent = 'DIBLOKIR';
          statusEl.className = 'px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-50 text-rose-700 border border-rose-200';
        }

        document.getElementById('infoWhatsapp').textContent = user.whatsapp || '-';
        document.getElementById('infoGender').textContent = user.gender || '-';
        document.getElementById('infoBirthDate').textContent = user.birth_date || '-';
        
        const locParts = [user.city, user.province, user.country].filter(Boolean);
        document.getElementById('infoLocation').textContent = locParts.length ? locParts.join(', ') : '-';
        document.getElementById('infoAddress').textContent = user.address || '-';
        document.getElementById('infoCreatedAt').textContent = user.created_at || '-';

        document.getElementById('modalUserInfo').classList.remove('hidden');
      }

      function openModalUserEdit(user) {
        if (!user) return;
        document.getElementById('editUserId').value = user.id || '';
        document.getElementById('editUserName').value = user.name || '';
        document.getElementById('editUserUsername').value = user.username || '';
        document.getElementById('editUserEmail').value = user.email || '';
        document.getElementById('editUserWhatsapp').value = user.whatsapp || '';
        document.getElementById('editUserRole').value = (user.role || 'sahabat').toLowerCase();
        document.getElementById('editUserStatus').value = (user.status || 'active').toLowerCase();
        document.getElementById('modalUserEdit').classList.remove('hidden');
      }

      function openModalUserReset(user) {
        if (!user) return;
        document.getElementById('resetUserId').value = user.id || '';
        document.getElementById('resetUserDisplayName').textContent = (user.name || '') + ' (@' + (user.username || '') + ')';
        document.getElementById('resetNewPassword').value = '';
        document.getElementById('modalUserResetPassword').classList.remove('hidden');
      }
    </script>
  <?php endif; ?>

</body>
</html>
