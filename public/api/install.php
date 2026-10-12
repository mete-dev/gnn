<?php
require_once __DIR__ . '/config.php';

try {
    $db = getDBConnection();
    
    // Read SQL file
    $sqlContent = file_get_contents(__DIR__ . '/database.sql');
    
    // Split statements safely
    $statements = array_filter(array_map('trim', explode(';', $sqlContent)));
    
    foreach ($statements as $stmtSql) {
        if (!empty($stmtSql)) {
            try {
                $db->exec($stmtSql);
            } catch (Exception $e) {
                // Ignore table already exists errors
            }
        }
    }

    // Auto-migrate missing columns for existing articles table
    $columnsToAdd = [
        'sub_category' => "VARCHAR(100) DEFAULT ''",
        'caption' => "TEXT DEFAULT NULL",
        'breaking' => "TINYINT(1) DEFAULT 0",
        'featured' => "TINYINT(1) DEFAULT 0",
        'popular_rank' => "INT DEFAULT 0",
        'bullets' => "TEXT DEFAULT NULL",
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
        'status' => "VARCHAR(50) DEFAULT 'publish'"
    ];

    foreach ($columnsToAdd as $col => $def) {
        try {
            $db->exec("ALTER TABLE articles ADD COLUMN `$col` $def");
        } catch (Exception $ex) {
            // Ignore if column already exists
        }
    }
    
    // Seed initial users if empty
    $stmtUserCheck = $db->query("SELECT COUNT(*) as cnt FROM users");
    if ($stmtUserCheck->fetch()['cnt'] == 0) {
        $insUser = $db->prepare("INSERT INTO users (id, username, email, name, role, password, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insUser->execute(['usr-1', 'admingnn', 'admingnn@gnn.id', 'Admin Utama GNN', 'admin', 'a@3etbwsye3', 'active']);
    }
    
    // Seed initial articles if empty
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM articles");
    $row = $stmt->fetch();
    
    if ($row['cnt'] == 0) {
        $articles = [
            [
                'id' => 'gnn-101',
                'category' => 'KABAR BAIK',
                'subCategory' => 'PRESTASI DUNIA',
                'title' => 'Sempat Menguat, Kini 1 USD Kembali ke Rp18.000',
                'content' => 'Kawasan Gunung Bromo di Jawa Timur kembali mencuri perhatian dunia sebagai salah satu destinasi wisata alam terpopuler dan terindah di Asia Tenggara.',
                'image' => 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&w=1200&q=80',
                'caption' => 'Kawasan Gunung Bromo yang mempesona saat matahari terbit di Jawa Timur.',
                'breaking' => 1,
                'featured' => 1,
                'popularRank' => 1,
                'bullets' => ["Gunung Bromo dinobatkan taman nasional terfavorit di Asia", "Kearifan lokal Suku Tengger jadi pilar konservasi lingkungan"],
                'date' => 'Selasa, 29 Sep 2026',
                'timeAgo' => '5 menit lalu',
                'author' => 'Fadhil Al Anshori',
                'authorEmail' => 'fadhil@gnn.id',
                'editor' => 'Tim Redaksi GNN',
                'location' => 'Probolinggo, Good News Nusantara',
                'likes' => 842,
                'views' => 32400,
                'status' => 'publish'
            ],
            [
                'id' => 'gnn-102',
                'category' => 'INSPIRASI',
                'subCategory' => 'ANAK BANGSA',
                'title' => 'Inovator Muda Sleman Ciptakan Filter Air Tenaga Surya, Bantu 40 Desa Bebas Krisis Air Bersih',
                'content' => 'Sekelompok mahasiswa teknik asal Sleman, Yogyakarta, berhasil mengembangkan alat penyaring air portabel bertenaga surya bernama TirtaSurya yang mampu memurnikan air keruh dan payau menjadi air siap minum dengan standar WHO.',
                'image' => 'https://images.unsplash.com/photo-1544717305-2782549b5136?auto=format&fit=crop&w=1200&q=80',
                'caption' => 'Anak-anak sekolah di pelosok desa menikmati air minum bersih hasil filtrasi bertenaga surya.',
                'breaking' => 1,
                'featured' => 0,
                'popularRank' => 2,
                'bullets' => ["Alat pemurni air bertenaga surya dengan biaya operasional Rp0", "Telah alirkan air bersih ke 40 desa rawan kekeringan"],
                'date' => 'Selasa, 29 Sep 2026',
                'timeAgo' => '20 menit lalu',
                'author' => 'Annisa Maharani',
                'authorEmail' => 'annisa@gnn.id',
                'editor' => 'Rizky Ramadhan',
                'location' => 'Yogyakarta, Good News Nusantara',
                'likes' => 650,
                'views' => 25100,
                'status' => 'publish'
            ]
        ];
        
        $insert = $db->prepare("INSERT INTO articles (id, category, sub_category, title, content, image, caption, breaking, featured, popular_rank, bullets, date, time_ago, author, author_email, editor, location, likes, views, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($articles as $a) {
            $insert->execute([
                $a['id'],
                $a['category'],
                $a['subCategory'],
                $a['title'],
                $a['content'],
                $a['image'],
                $a['caption'],
                $a['breaking'],
                $a['featured'],
                $a['popularRank'],
                json_encode($a['bullets']),
                $a['date'],
                $a['timeAgo'],
                $a['author'],
                $a['authorEmail'],
                $a['editor'],
                $a['location'],
                $a['likes'],
                $a['views'],
                $a['status']
            ]);
        }
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Database berhasil diinisialisasi dan tabel telah diperbarui!"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Instalasi Gagal: " . $e->getMessage()
    ]);
}
