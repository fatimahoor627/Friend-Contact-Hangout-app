-- ==============================================================================
-- FRIENDS HANGOUT DATABASE SCHEMA
-- Compatible with MySQL 5.7+ / 8.0+ & MariaDB in Laragon / phpMyAdmin / HeidiSQL
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `friend-app` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `friend-app`;

-- 1. Users Table (Account & Authentication)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `avatar_url` VARCHAR(500) NULL,
    `bio` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Friends Contact Directory Table (Contacts, Qualities, Behavior, Location, Occupation)
CREATE TABLE IF NOT EXISTS `friends` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(40) NULL,
    `email` VARCHAR(150) NULL,
    `location` VARCHAR(255) NULL,
    `occupation` VARCHAR(150) NULL,
    `qualities` VARCHAR(255) NULL,
    `behavior_notes` TEXT NULL,
    `circle` VARCHAR(50) DEFAULT 'Close Friend',
    `avatar_url` VARCHAR(500) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_friend_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Hangouts Table (Meetups with Kaha/Where, Kio/Why, Kese/How)
CREATE TABLE IF NOT EXISTS `hangouts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `purpose` TEXT NULL,
    `plan_logistics` TEXT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'Social',
    `location_name` VARCHAR(255) NOT NULL,
    `address` VARCHAR(255) NULL,
    `latitude` DECIMAL(10, 7) NULL,
    `longitude` DECIMAL(10, 7) NULL,
    `event_date` DATE NULL,
    `event_time` TIME NULL,
    `has_poll` TINYINT(1) DEFAULT 0,
    `status` ENUM('upcoming', 'voting', 'past', 'cancelled') DEFAULT 'upcoming',
    `creator_id` INT NOT NULL,
    `cover_image` VARCHAR(500) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_creator` (`creator_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Hangout Assigned Tasks Table (Task Management per Hangout)
CREATE TABLE IF NOT EXISTS `hangout_tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hangout_id` INT NOT NULL,
    `friend_id` INT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_completed` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_task_hangout` (`hangout_id`),
    INDEX `idx_task_friend` (`friend_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Hangout Invited Friends Table (Who is added to the Hangout)
CREATE TABLE IF NOT EXISTS `hangout_invited_friends` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hangout_id` INT NOT NULL,
    `friend_id` INT NOT NULL,
    `status` ENUM('invited', 'going', 'maybe', 'busy') DEFAULT 'invited',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_hangout_invited` (`hangout_id`, `friend_id`),
    INDEX `idx_invited_hangout` (`hangout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Hangout Attendees (RSVP) Table
CREATE TABLE IF NOT EXISTS `hangout_attendees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hangout_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `status` ENUM('attending', 'maybe', 'declined') DEFAULT 'attending',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_hangout_user` (`hangout_id`, `user_id`),
    INDEX `idx_hangout` (`hangout_id`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Hangout Polls Table
CREATE TABLE IF NOT EXISTS `hangout_polls` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hangout_id` INT NOT NULL,
    `question` VARCHAR(255) NOT NULL,
    `is_closed` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_poll_hangout` (`hangout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Hangout Poll Options Table
CREATE TABLE IF NOT EXISTS `hangout_poll_options` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `poll_id` INT NOT NULL,
    `option_text` VARCHAR(255) NOT NULL,
    `option_datetime` DATETIME NULL,
    INDEX `idx_option_poll` (`poll_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Hangout Poll Votes Table
CREATE TABLE IF NOT EXISTS `hangout_poll_votes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `option_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_poll_vote` (`option_id`, `user_id`),
    INDEX `idx_vote_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Hangout Messages (Live Group Chat) Table
CREATE TABLE IF NOT EXISTS `hangout_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hangout_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_message_hangout` (`hangout_id`),
    INDEX `idx_message_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Hangout Activities Table (Audit & Activity Log)
CREATE TABLE IF NOT EXISTS `hangout_activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hangout_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `action_type` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_activity_hangout` (`hangout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `link_hangout_id` INT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notification_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
