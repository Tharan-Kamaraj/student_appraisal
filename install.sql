-- Student Appraisal System (SAS) - Install Script
-- Database: student_appraisal

CREATE DATABASE IF NOT EXISTS student_appraisal CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE student_appraisal;

-- Users table (students and mentors/admins)
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','mentor','admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Student personal profiles (1:1 with users)
CREATE TABLE IF NOT EXISTS student_profiles (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  roll_no VARCHAR(30) DEFAULT NULL,
  program VARCHAR(50) DEFAULT NULL,
  department VARCHAR(80) DEFAULT NULL,
  year_of_study TINYINT UNSIGNED DEFAULT NULL,
  section VARCHAR(10) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  dob DATE DEFAULT NULL,
  address_line VARCHAR(180) DEFAULT NULL,
  city VARCHAR(80) DEFAULT NULL,
  state VARCHAR(80) DEFAULT NULL,
  pincode VARCHAR(12) DEFAULT NULL,
  guardian_name VARCHAR(100) DEFAULT NULL,
  guardian_phone VARCHAR(20) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- (audit_logs will be created at the end, after dependent tables)

-- Appraisals table
CREATE TABLE IF NOT EXISTS appraisals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  academic_gpa TINYINT UNSIGNED DEFAULT 0,            -- /10
  academic_project TINYINT UNSIGNED DEFAULT 0,        -- /10
  academic_certifications TINYINT UNSIGNED DEFAULT 0, -- /5
  academic_language TINYINT UNSIGNED DEFAULT 0,       -- /5
  academic_attendance TINYINT UNSIGNED DEFAULT 0,     -- /10

  cocurricular_events TINYINT UNSIGNED DEFAULT 0,     -- /15
  cocurricular_innovation TINYINT UNSIGNED DEFAULT 0, -- /10
  cocurricular_membership TINYINT UNSIGNED DEFAULT 0, -- /5
  cocurricular_community TINYINT UNSIGNED DEFAULT 0,  -- /5
  cocurricular_competitive TINYINT UNSIGNED DEFAULT 0,-- /5

  personality_leadership TINYINT UNSIGNED DEFAULT 0,  -- /5
  personality_softskills TINYINT UNSIGNED DEFAULT 0,  -- /5
  personality_feedback TINYINT UNSIGNED DEFAULT 0,    -- /5
  personality_awards TINYINT UNSIGNED DEFAULT 0,      -- /5

  total SMALLINT UNSIGNED DEFAULT 0,
  grade VARCHAR(2) DEFAULT NULL,
  status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  mentor_remarks VARCHAR(500) DEFAULT NULL,

  details JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appraisal_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Indexes
CREATE INDEX idx_appraisals_student ON appraisals(student_id);
CREATE INDEX idx_users_role ON users(role);

-- Honors / Awards (e.g., Best Outgoing Student)
CREATE TABLE IF NOT EXISTS honors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  appraisal_id INT UNSIGNED DEFAULT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  title ENUM('best_outgoing') NOT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_honors_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_honors_appraisal FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_title_year (title, year)
) ENGINE=InnoDB;
