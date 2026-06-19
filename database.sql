-- School Management System Database Schema
-- Database: school_db

CREATE DATABASE IF NOT EXISTS `school_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_db`;

-- 1. Branches Table
CREATE TABLE IF NOT EXISTS `branches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `address` VARCHAR(255) NULL,
    `phone` VARCHAR(20) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Academic Levels Table
CREATE TABLE IF NOT EXISTS `levels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Subjects Table
CREATE TABLE IF NOT EXISTS `subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Users Table (Super Admin, Teacher/Staff)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin', 'teacher') NOT NULL DEFAULT 'teacher',
    `branch_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Students Table
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `gender` ENUM('Male', 'Female') NOT NULL,
    `dob` DATE NULL,
    `phone` VARCHAR(20) NULL,
    `branch_id` INT NOT NULL,
    `level_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Attendance Table
CREATE TABLE IF NOT EXISTS `attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `status` ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL DEFAULT 'Present',
    `marked_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_student_date` (`student_id`, `date`),
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. Exam Results / Marks Table
CREATE TABLE IF NOT EXISTS `exams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `score` DECIMAL(5,2) NOT NULL,
    `exam_date` DATE NOT NULL,
    `teacher_id` INT NULL,
    `remarks` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Insert Seed Data
-- 1. Branches
INSERT INTO `branches` (`id`, `name`, `address`, `phone`) VALUES
(1, 'Phnom Penh Campus', 'No. 123, St. 271, Phnom Penh', '+855 23 888 999'),
(2, 'Siem Reap Campus', 'No. 45, National Road 6, Siem Reap', '+855 63 777 888');

-- 2. Levels
INSERT INTO `levels` (`id`, `name`, `description`) VALUES
(1, 'Grade 10', 'High School Sophomore Level'),
(2, 'Grade 11', 'High School Junior Level'),
(3, 'Grade 12', 'High School Senior Level');

-- 3. Subjects
INSERT INTO `subjects` (`id`, `name`, `description`) VALUES
(1, 'Mathematics', 'Study of numbers, equations, and shapes'),
(2, 'English', 'English grammar, vocabulary, and literature'),
(3, 'Physics', 'Introduction to physical laws and theories'),
(4, 'Chemistry', 'Study of elements and chemical compounds'),
(5, 'Biology', 'Study of life and living organisms'),
(6, 'History', 'World history and social studies'),
(7, 'Literature', 'Reading, analyzing, and writing literature pieces');

-- 4. Users (admin123 and teacher123)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `branch_id`) VALUES
(1, 'Super Admin User', 'admin@school.com', '$2y$12$kLa1yLnu1gIdeP/Cdoo9cuOgbAJ0T0JEZx2IkHBJIU7BoRhn/GP7u', 'super_admin', NULL),
(2, 'Sok Dara (Teacher)', 'dara@school.com', '$2y$12$.4sAYxJQwwScrh/7Q.5l/OFirOyo69cuvkU9g6ZcccY.sa6I3xpH.', 'teacher', 1),
(3, 'Keo Srey (Teacher)', 'srey@school.com', '$2y$12$.4sAYxJQwwScrh/7Q.5l/OFirOyo69cuvkU9g6ZcccY.sa6I3xpH.', 'teacher', 2);

-- 5. Students
INSERT INTO `students` (`id`, `student_code`, `name`, `gender`, `dob`, `phone`, `branch_id`, `level_id`) VALUES
(1, 'STU001', 'Chan Rotha', 'Male', '2009-05-12', '012 345 678', 1, 1),
(2, 'STU002', 'Sok Piseth', 'Male', '2008-08-20', '098 765 432', 1, 2),
(3, 'STU003', 'Lim Nary', 'Female', '2007-11-03', '015 555 444', 1, 3),
(4, 'STU004', 'Seng Cheata', 'Female', '2009-02-15', '077 111 222', 2, 1),
(5, 'STU005', 'Nguon Vibol', 'Male', '2008-04-25', '088 999 000', 2, 2);

-- 6. Attendance (Sample)
INSERT INTO `attendance` (`student_id`, `date`, `status`, `marked_by`) VALUES
(1, CURDATE(), 'Present', 2),
(2, CURDATE(), 'Late', 2),
(3, CURDATE(), 'Present', 2),
(4, CURDATE(), 'Absent', 3),
(5, CURDATE(), 'Present', 3);

-- 7. Exam Results (Sample)
INSERT INTO `exams` (`student_id`, `subject_id`, `score`, `exam_date`, `teacher_id`, `remarks`) VALUES
(1, 1, 85.50, '2026-06-15', 2, 'Excellent logical skills'),
(1, 2, 78.00, '2026-06-16', 2, 'Good speaking ability'),
(2, 1, 92.00, '2026-06-15', 2, 'Outstanding score'),
(2, 2, 84.50, '2026-06-16', 2, 'Very good comprehension'),
(3, 1, 65.00, '2026-06-15', 2, 'Needs improvement in algebra'),
(4, 1, 74.00, '2026-06-15', 3, 'Average performance'),
(5, 1, 88.00, '2026-06-15', 3, 'Solid performance');
