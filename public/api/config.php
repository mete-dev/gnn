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
