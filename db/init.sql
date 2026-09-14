SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `landings` (
    `id`          INT          NOT NULL,
    `parent_sku`  VARCHAR(100) NOT NULL,
    `country`     VARCHAR(10)  NOT NULL,
    `is_master`   TINYINT(1)   DEFAULT 0,
    `title`       VARCHAR(500) DEFAULT NULL,
    `description` TEXT,
    `landing_url` VARCHAR(500) DEFAULT NULL,
    `image`       VARCHAR(500) DEFAULT NULL,
    `synced_at`   DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_sku`     (`parent_sku`),
    INDEX `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `testimonials` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `landing_id`  INT          NOT NULL,
    `author_name` VARCHAR(200) NOT NULL,
    `text`        TEXT         NOT NULL,
    `rating`      TINYINT      DEFAULT NULL,
    `gender`      ENUM('male','female','other') DEFAULT NULL,
    `url`         VARCHAR(500) DEFAULT NULL,
    `is_active`   TINYINT(1)   DEFAULT 1,
    `sort_order`  INT          DEFAULT 0,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`landing_id`) REFERENCES `landings`(`id`) ON DELETE CASCADE,
    INDEX `idx_landing` (`landing_id`),
    INDEX `idx_sort`    (`landing_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `testimonial_images` (
    `id`             INT          NOT NULL AUTO_INCREMENT,
    `testimonial_id` INT          NOT NULL,
    `filename`       VARCHAR(300) NOT NULL,
    `thumbnail`      VARCHAR(300) DEFAULT NULL,
    `sort_order`     INT          DEFAULT 0,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`testimonial_id`) REFERENCES `testimonials`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT          NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `change_log` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `user_id`    INT          DEFAULT NULL,
    `action`     VARCHAR(50)  NOT NULL,
    `entity`     VARCHAR(50)  NOT NULL DEFAULT 'testimonial',
    `entity_id`  INT          NOT NULL,
    `landing_id` INT          DEFAULT NULL,
    `details`    JSON         DEFAULT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_entity`  (`entity`, `entity_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: username=admin, password=password
-- Hash generated with: password_hash('password', PASSWORD_BCRYPT)
INSERT IGNORE INTO `users` (`username`, `password_hash`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
