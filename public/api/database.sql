-- Database Schema for Good News Nusantara (MySQL & SQLite Compatible)

CREATE TABLE IF NOT EXISTS `users` (
  `id` VARCHAR(100) PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'sahabat',
  `password` VARCHAR(255) DEFAULT NULL,
  `avatar` TEXT DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `articles` (
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
  `date` VARCHAR(100) NOT NULL,
  `time_ago` VARCHAR(100) DEFAULT 'Baru saja',
  `author` VARCHAR(150) NOT NULL,
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
);

CREATE TABLE IF NOT EXISTS `comments` (
  `id` VARCHAR(100) PRIMARY KEY,
  `article_id` VARCHAR(100) NOT NULL,
  `user` VARCHAR(150) NOT NULL,
  `text` TEXT NOT NULL,
  `time` VARCHAR(100) DEFAULT 'Baru saja',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `videos` (
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
);

CREATE TABLE IF NOT EXISTS `gallery` (
  `id` VARCHAR(100) PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `image_url` TEXT NOT NULL,
  `file_size_kb` INT DEFAULT 0,
  `caption` TEXT DEFAULT NULL,
  `author` VARCHAR(150) NOT NULL,
  `author_email` VARCHAR(150) NOT NULL,
  `date` VARCHAR(100) NOT NULL,
  `used_count` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);
