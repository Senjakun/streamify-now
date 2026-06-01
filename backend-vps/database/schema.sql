-- ============================================
-- PLAYALL.ME DATABASE SCHEMA
-- MySQL/MariaDB
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS streamify_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE streamify_db;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    rank_label ENUM('Rakyat', 'Ksatria', 'Duke', 'Tuhan') DEFAULT 'Rakyat',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB;

-- ============================================
-- USER ROLES TABLE (Security Best Practice)
-- ============================================
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role ENUM('user', 'moderator', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id, role),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- ============================================
-- CONTENT TABLE (Anime, Movies, Manga)
-- ============================================
CREATE TABLE IF NOT EXISTS content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    title_alt VARCHAR(255) DEFAULT NULL,
    description TEXT,
    type ENUM('anime', 'movie', 'manga') NOT NULL,
    status ENUM('ongoing', 'completed', 'upcoming') DEFAULT 'ongoing',
    poster_url VARCHAR(500),
    banner_url VARCHAR(500),
    rating DECIMAL(3,1) DEFAULT 0,
    year INT,
    genres JSON,
    source_url VARCHAR(500), -- URL sumber scraping
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_slug (slug),
    FULLTEXT idx_search (title, title_alt, description)
) ENGINE=InnoDB;

-- ============================================
-- EPISODES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    episode_number INT NOT NULL,
    title VARCHAR(255),
    thumbnail_url VARCHAR(500),
    video_url VARCHAR(500),
    duration INT DEFAULT 0, -- in seconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    UNIQUE KEY unique_episode (content_id, episode_number),
    INDEX idx_content_id (content_id)
) ENGINE=InnoDB;

-- ============================================
-- CHAPTERS TABLE (For Manga)
-- ============================================
CREATE TABLE IF NOT EXISTS chapters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    chapter_number DECIMAL(6,1) NOT NULL,
    title VARCHAR(255),
    images JSON, -- Array of image URLs
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    UNIQUE KEY unique_chapter (content_id, chapter_number),
    INDEX idx_content_id (content_id)
) ENGINE=InnoDB;

-- ============================================
-- BOOKMARKS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bookmark (user_id, content_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- ============================================
-- COMMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    episode_id INT DEFAULT NULL,
    chapter_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL, -- For replies
    comment_text TEXT NOT NULL,
    likes_count INT DEFAULT 0,
    is_spoiler BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE SET NULL,
    FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_content_id (content_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- ============================================
-- COMMENT LIKES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS comment_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, comment_id)
) ENGINE=InnoDB;

-- ============================================
-- WATCH HISTORY TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    episode_id INT DEFAULT NULL,
    chapter_id INT DEFAULT NULL,
    progress INT DEFAULT 0, -- in seconds for video, page number for manga
    completed BOOLEAN DEFAULT FALSE,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    UNIQUE KEY unique_history (user_id, content_id, episode_id, chapter_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- ============================================
-- SCRAPE LOGS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS scrape_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_name VARCHAR(100) NOT NULL,
    source_url VARCHAR(500) NOT NULL,
    status ENUM('pending', 'running', 'success', 'failed') DEFAULT 'pending',
    items_found INT DEFAULT 0,
    items_added INT DEFAULT 0,
    error_message TEXT,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_source_name (source_name)
) ENGINE=InnoDB;

-- ============================================
-- INSERT DEFAULT ADMIN USER
-- Password: admin123 (ganti setelah deploy!)
-- ============================================
INSERT INTO users (username, email, password_hash) VALUES 
('admin', 'admin@playall.me', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO user_roles (user_id, role) VALUES (1, 'admin');
