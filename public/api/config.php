<?php
// Config for Good News Nusantara PHP Backend (cPanel MySQL)

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
    header("Content-Type: application/json; charset=UTF-8");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database Credentials - Pre-configured with cPanel MySQL user credentials
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'goodnews_database'); 
define('DB_USER', getenv('DB_USER') ?: 'goodnews_admin');
define('DB_PASS', getenv('DB_PASS') ?: 'rAhBFGvjTFQkV9_');

function ensureTableSchemas($pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // 1. Ensure all core tables exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` VARCHAR(100) PRIMARY KEY,
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `email` VARCHAR(150) NOT NULL UNIQUE,
            `name` VARCHAR(150) NOT NULL,
            `role` VARCHAR(50) NOT NULL DEFAULT 'sahabat',
            `password` VARCHAR(255) DEFAULT NULL,
            `avatar` LONGTEXT DEFAULT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'active',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `articles` (
            `id` VARCHAR(100) PRIMARY KEY,
            `category` VARCHAR(100) NOT NULL,
            `sub_category` VARCHAR(100) DEFAULT '',
            `title` VARCHAR(255) NOT NULL,
            `content` LONGTEXT NOT NULL,
            `image` LONGTEXT NOT NULL,
            `caption` LONGTEXT DEFAULT NULL,
            `breaking` TINYINT(1) DEFAULT 0,
            `featured` TINYINT(1) DEFAULT 0,
            `popular_rank` INT DEFAULT 0,
            `bullets` LONGTEXT DEFAULT NULL,
            `date` VARCHAR(100) DEFAULT '',
            `time_ago` VARCHAR(100) DEFAULT 'Baru saja',
            `author` VARCHAR(150) DEFAULT '',
            `author_email` VARCHAR(150) DEFAULT '',
            `editor` VARCHAR(150) DEFAULT 'Tim Redaksi GNN',
            `location` VARCHAR(150) DEFAULT 'Good News Nusantara',
            `likes` INT DEFAULT 0,
            `views` INT DEFAULT 0,
            `keywords` TEXT DEFAULT NULL,
            `image_credit` VARCHAR(255) DEFAULT '',
            `is_photo_gallery` TINYINT(1) DEFAULT 0,
            `photo_count` INT DEFAULT 0,
            `video_duration` VARCHAR(50) DEFAULT '',
            `status` VARCHAR(50) DEFAULT 'publish',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `comments` (
            `id` VARCHAR(100) PRIMARY KEY,
            `article_id` VARCHAR(100) NOT NULL,
            `user` VARCHAR(150) NOT NULL,
            `text` TEXT NOT NULL,
            `time` VARCHAR(100) DEFAULT 'Baru saja',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `videos` (
            `id` VARCHAR(100) PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `youtube_url` TEXT NOT NULL,
            `youtube_id` VARCHAR(100) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `category` VARCHAR(100) DEFAULT 'EKONOMI',
            `sub_category` VARCHAR(100) DEFAULT 'Ekonomi',
            `author` VARCHAR(150) DEFAULT 'Tim Studio GNN',
            `author_email` VARCHAR(150) DEFAULT 'admin@gnn.id',
            `date` VARCHAR(100) NOT NULL,
            `duration` VARCHAR(50) DEFAULT '03:45',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `gallery` (
            `id` VARCHAR(100) PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `image_url` LONGTEXT NOT NULL,
            `file_size_kb` INT DEFAULT 0,
            `caption` LONGTEXT DEFAULT NULL,
            `author` VARCHAR(150) NOT NULL,
            `author_email` VARCHAR(150) NOT NULL,
            `date` VARCHAR(100) NOT NULL,
            `used_count` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Dynamic column check and ALTER TABLE for MySQL / MariaDB
        if ($driver === 'mysql') {
            $tableColumns = [
                'articles' => [
                    'sub_category' => "VARCHAR(100) DEFAULT ''",
                    'caption' => "LONGTEXT DEFAULT NULL",
                    'breaking' => "TINYINT(1) DEFAULT 0",
                    'featured' => "TINYINT(1) DEFAULT 0",
                    'popular_rank' => "INT DEFAULT 0",
                    'bullets' => "LONGTEXT DEFAULT NULL",
                    'date' => "VARCHAR(100) DEFAULT ''",
                    'time_ago' => "VARCHAR(100) DEFAULT 'Baru saja'",
                    'author' => "VARCHAR(150) DEFAULT ''",
                    'author_email' => "VARCHAR(150) DEFAULT ''",
                    'editor' => "VARCHAR(150) DEFAULT 'Tim Redaksi GNN'",
                    'location' => "VARCHAR(150) DEFAULT 'Good News Nusantara'",
                    'likes' => "INT DEFAULT 0",
                    'views' => "INT DEFAULT 0",
                    'keywords' => "TEXT DEFAULT NULL",
                    'image_credit' => "VARCHAR(255) DEFAULT ''",
                    'is_photo_gallery' => "TINYINT(1) DEFAULT 0",
                    'photo_count' => "INT DEFAULT 0",
                    'video_duration' => "VARCHAR(50) DEFAULT ''",
                    'status' => "VARCHAR(50) DEFAULT 'publish'",
                    'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
                    'updated_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP"
                ],
                'videos' => [
                    'title' => "VARCHAR(255) NOT NULL DEFAULT ''",
                    'youtube_url' => "TEXT DEFAULT NULL",
                    'youtube_id' => "VARCHAR(100) NOT NULL DEFAULT ''",
                    'description' => "TEXT DEFAULT NULL",
                    'category' => "VARCHAR(100) DEFAULT 'EKONOMI'",
                    'sub_category' => "VARCHAR(100) DEFAULT 'Ekonomi'",
                    'author' => "VARCHAR(150) DEFAULT 'Tim Studio GNN'",
                    'author_email' => "VARCHAR(150) DEFAULT 'admin@gnn.id'",
                    'date' => "VARCHAR(100) DEFAULT ''",
                    'duration' => "VARCHAR(50) DEFAULT '03:45'",
                    'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP"
                ],
                'gallery' => [
                    'title' => "VARCHAR(255) NOT NULL DEFAULT ''",
                    'image_url' => "LONGTEXT DEFAULT NULL",
                    'file_size_kb' => "INT DEFAULT 0",
                    'caption' => "LONGTEXT DEFAULT NULL",
                    'author' => "VARCHAR(150) DEFAULT ''",
                    'author_email' => "VARCHAR(150) DEFAULT ''",
                    'date' => "VARCHAR(100) DEFAULT ''",
                    'used_count' => "INT DEFAULT 0",
                    'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP"
                ],
                'users' => [
                    'username' => "VARCHAR(100) NOT NULL DEFAULT ''",
                    'email' => "VARCHAR(150) NOT NULL DEFAULT ''",
                    'name' => "VARCHAR(150) NOT NULL DEFAULT ''",
                    'role' => "VARCHAR(50) NOT NULL DEFAULT 'sahabat'",
                    'password' => "VARCHAR(255) DEFAULT NULL",
                    'avatar' => "LONGTEXT DEFAULT NULL",
                    'status' => "VARCHAR(50) NOT NULL DEFAULT 'active'",
                    'whatsapp' => "VARCHAR(50) DEFAULT ''",
                    'gender' => "VARCHAR(20) DEFAULT ''",
                    'birth_date' => "VARCHAR(50) DEFAULT ''",
                    'country' => "VARCHAR(100) DEFAULT 'Indonesia'",
                    'province' => "VARCHAR(100) DEFAULT ''",
                    'city' => "VARCHAR(100) DEFAULT ''",
                    'address' => "TEXT DEFAULT NULL",
                    'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP"
                ],
                'comments' => [
                    'article_id' => "VARCHAR(100) NOT NULL DEFAULT ''",
                    'user' => "VARCHAR(150) NOT NULL DEFAULT ''",
                    'text' => "TEXT DEFAULT NULL",
                    'time' => "VARCHAR(100) DEFAULT 'Baru saja'",
                    'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP"
                ]
            ];

            foreach ($tableColumns as $tableName => $cols) {
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM `$tableName`");
                    $existingCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($cols as $colName => $def) {
                        if (!in_array($colName, $existingCols)) {
                            $pdo->exec("ALTER TABLE `$tableName` ADD COLUMN `$colName` $def");
                        }
                    }
                } catch (Exception $e) {}
            }

            // Fix nullable and capacity columns
            $modifies = [
                "ALTER TABLE `articles` MODIFY COLUMN `image` LONGTEXT NOT NULL",
                "ALTER TABLE `articles` MODIFY COLUMN `content` LONGTEXT NOT NULL",
                "ALTER TABLE `articles` MODIFY COLUMN `caption` LONGTEXT DEFAULT NULL",
                "ALTER TABLE `articles` MODIFY COLUMN `bullets` LONGTEXT DEFAULT NULL",
                "ALTER TABLE `gallery` MODIFY COLUMN `image_url` LONGTEXT NOT NULL",
                "ALTER TABLE `users` MODIFY COLUMN `avatar` LONGTEXT DEFAULT NULL",
                "ALTER TABLE `users` MODIFY COLUMN `password` VARCHAR(255) DEFAULT NULL",
                "ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'sahabat'",
                "ALTER TABLE `users` MODIFY COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP"
            ];

            foreach ($modifies as $sql) {
                try {
                    $pdo->exec($sql);
                } catch (Exception $e) {}
            }

            // Ensure administrator account (admin@gnn.my.id / Gnn6908) exists and fadhilalanshor511@gmail.com is admin
            try {
                $adminEmail = 'admin@gnn.my.id';
                $adminPassHash = password_hash('Gnn6908', PASSWORD_DEFAULT);
                $stmtAdmin = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? OR LOWER(username) = 'admin'");
                $stmtAdmin->execute([$adminEmail]);
                $existingAdmin = $stmtAdmin->fetch();
                if (!$existingAdmin) {
                    $pdo->prepare("INSERT INTO users (id, username, email, name, role, password, avatar, status, created_at) VALUES ('usr-admin-main', 'admin', ?, 'Administrator GNN', 'admin', ?, '', 'active', CURRENT_TIMESTAMP)")
                        ->execute([$adminEmail, $adminPassHash]);
                } else {
                    $pdo->prepare("UPDATE users SET email = ?, role = 'admin', password = ? WHERE id = ?")
                        ->execute([$adminEmail, $adminPassHash, $existingAdmin['id']]);
                }

                $pdo->exec("UPDATE users SET role = 'admin' WHERE LOWER(email) = 'fadhilalanshor511@gmail.com'");

                // Repair any missing or empty article images
                $pdo->exec("UPDATE articles SET image = 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?auto=format&fit=crop&w=1200&q=80' WHERE id = 'gnn-101' AND (image IS NULL OR image = '' OR LENGTH(TRIM(image)) < 5)");
                $pdo->exec("UPDATE articles SET image = 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80' WHERE image IS NULL OR image = '' OR LENGTH(TRIM(image)) < 5");

                // Seed Saintek & Sejarah authentic articles if not already present
                $seedArticles = [
                    [
                        'id' => 'gnn-st-2026-satelit',
                        'category' => 'SAINTEK',
                        'sub_category' => 'Teknologi',
                        'title' => 'Inovasi Satelit Nusantara: Perluas Jangkauan Internet Cepat Hingga Pelosok 3T Indonesia',
                        'content' => 'Kemajuan teknologi telekomunikasi di Indonesia terus mencatatkan tonggak sejarah baru dengan peluncuran dan operasionalisasi satelit generasi terbaru yang dirancang untuk memperluas akses internet pita lebar (broadband) ke daerah tertinggal, terdepan, dan terluar (3T). Program pemerataan konektivitas digital ini mendukung digitalisasi pendidikan, layanan kesehatan jarak jauh (telemedicine), serta percepatan ekonomi digital bagi masyarakat di kepulauan terpencil demi mewujudkan Indonesia Emas yang inklusif.',
                        'image' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1200&q=80',
                        'caption' => 'Satelit komunikasi Nusantara beroperasi di orbit untuk pemerataan jaringan internet daerah 3T',
                        'author' => 'Tim Riset & Teknologi GNN',
                        'popular_rank' => 95,
                        'views' => 1840,
                        'date' => '13 Oct 2026'
                    ],
                    [
                        'id' => 'gnn-st-2026-padi',
                        'category' => 'SAINTEK',
                        'sub_category' => 'Sains',
                        'title' => 'Terobosan Riset BRIN: Kembangkan Varietas Benih Padi Tahan Salinitas dan Iklim Ekstrem',
                        'content' => 'Para peneliti Badan Riset dan Inovasi Nasional (BRIN) sukses mengembangkan galur padi mutan unggul yang memiliki ketahanan tinggi terhadap kadar garam (salinitas) dan kekeringan akibat perubahan iklim global. Temuan ilmiah ini menjadi terobosan strategis bagi upaya mewujudkan kedaulatan pangan berkelanjutan di berbagai lahan marjinal dan kawasan pesisir Indonesia.',
                        'image' => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&w=1200&q=80',
                        'caption' => 'Riset varietas benih padi unggul di laboratorium bioteknologi tanaman BRIN',
                        'author' => 'Desk Sains & Riset GNN',
                        'popular_rank' => 90,
                        'views' => 1420,
                        'date' => '12 Oct 2026'
                    ],
                    [
                        'id' => 'gnn-sej-2026-sriwijaya',
                        'category' => 'SOSIAL',
                        'sub_category' => 'Sejarah',
                        'title' => 'Misteri Kejayaan Kemaritiman Sriwijaya: Mengungkap Poros Niaga Rempah di Selat Malaka',
                        'content' => 'Kedatuan Sriwijaya yang berpusat di Pulau Sumatra tercatat dalam berbagai prasasti dan catatan musafir mancanegara sebagai penguasa jalur niaga maritim internasional yang disegani sejak abad ke-7 masehi. Melalui armada laut yang tangguh dan diplomasi terampil, Sriwijaya mengendalikan simpul dagang rempah Selat Malaka sekaligus menjadi pusat studi kebudayaan dan ilmu pengetahuan dunia kuno.',
                        'image' => 'https://images.unsplash.com/photo-1599561066246-8d8a17485368?auto=format&fit=crop&w=1200&q=80',
                        'caption' => 'Ilustrasi relief kapal layar bercadik nusantara zaman keemasan maritim Sriwijaya',
                        'author' => 'Desk Sejarah & Budaya GNN',
                        'popular_rank' => 88,
                        'views' => 1950,
                        'date' => '12 Oct 2026'
                    ],
                    [
                        'id' => 'gnn-sej-2026-tarumanagara',
                        'category' => 'SOSIAL',
                        'sub_category' => 'Sejarah',
                        'title' => 'Jejak Peradaban Tarumanagara: Menguak Pesan Abadi Raja Purnawarman dalam Prasasti Ciaruteun',
                        'content' => 'Prasasti Ciaruteun yang ditemukan di tepian Sungai Cisadane menyimpan guratan tapak kaki Raja Purnawarman yang melambangkan kekuasaan yang adil dan pelindung rakyat Tarumanagara. Pembangunan saluran irigasi Sungai Candrabhaga dan Gomati yang tercatat dalam prasasti Tugu membuktikan tingginya rekayasa teknologi tata kelola air dan pertanian nenek moyang bangsa Indonesia sejak abad ke-5 masehi.',
                        'image' => 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80',
                        'caption' => 'Situs cagar budaya dan batu prasasti peninggalan peradaban kerajaan kuno di Nusantara',
                        'author' => 'Desk Sejarah & Budaya GNN',
                        'popular_rank' => 85,
                        'views' => 1610,
                        'date' => '11 Oct 2026'
                    ]
                ];

                $stmtInsertArt = $pdo->prepare("INSERT IGNORE INTO articles (id, category, sub_category, title, content, image, caption, author, popular_rank, views, date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'publish', CURRENT_TIMESTAMP)");
                foreach ($seedArticles as $sa) {
                    $stmtInsertArt->execute([
                        $sa['id'], $sa['category'], $sa['sub_category'], $sa['title'],
                        $sa['content'], $sa['image'], $sa['caption'], $sa['author'],
                        $sa['popular_rank'], $sa['views'], $sa['date']
                    ]);
                }
            } catch (Exception $e) {}
        }
    } catch (Exception $e) {
        // Schema check safe fallback
    }
}

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $lastError = '';
    $dbNamesToTry = [
        DB_NAME,
        'goodnews_database',
        'goodnews_admin',
        'goodnews_gnn',
        'goodnews_db',
        'gnn_database'
    ];

    foreach ($dbNamesToTry as $dbName) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $dbName . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            ensureTableSchemas($pdo);
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e->getMessage();
            continue;
        }
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Koneksi Database MySQL Gagal. Error: " . $lastError
    ]);
    exit();
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    if (is_array($decoded) && !empty($decoded)) {
        return $decoded;
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    return is_array($decoded) ? $decoded : [];
}
