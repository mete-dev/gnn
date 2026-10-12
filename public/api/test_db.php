<?php
require_once __DIR__ . '/config.php';

header("Content-Type: application/json; charset=UTF-8");

try {
    $db = getDBConnection();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    $tables = [];
    if ($driver === 'mysql') {
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $artCount = 0;
    $artColumns = [];
    try {
        $stmtArt = $db->query("SELECT COUNT(*) as cnt FROM articles");
        $artCount = intval($stmtArt->fetch()['cnt'] ?? 0);
        $colStmt = $db->query("SHOW COLUMNS FROM articles");
        $artColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    $userCount = 0;
    $userColumns = [];
    try {
        $stmtUsr = $db->query("SELECT COUNT(*) as cnt FROM users");
        $userCount = intval($stmtUsr->fetch()['cnt'] ?? 0);
        $colStmt = $db->query("SHOW COLUMNS FROM users");
        $userColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    $videoCount = 0;
    $videoColumns = [];
    try {
        $stmtVid = $db->query("SELECT COUNT(*) as cnt FROM videos");
        $videoCount = intval($stmtVid->fetch()['cnt'] ?? 0);
        $colStmt = $db->query("SHOW COLUMNS FROM videos");
        $videoColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    echo json_encode([
        "success" => true,
        "status" => "Database Connected Successfully!",
        "driver" => $driver,
        "database" => DB_NAME,
        "user" => DB_USER,
        "tables" => $tables,
        "articles_count" => $artCount,
        "articles_columns" => $artColumns,
        "users_count" => $userCount,
        "users_columns" => $userColumns,
        "videos_count" => $videoCount,
        "videos_columns" => $videoColumns
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "status" => "Connection Error",
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
