CREATE DATABASE IF NOT EXISTS clinic_appointment_system;
USE clinic_appointment_system;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('student','admin') NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
    student_id INT PRIMARY KEY,
    student_number VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    course VARCHAR(100) NOT NULL,
    contact_no VARCHAR(30) DEFAULT NULL,
    CONSTRAINT fk_students_users FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patient_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    allergies TEXT,
    conditions TEXT,
    emergency_contact TEXT,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_records_students FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clinic_schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    time_slot TIME NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_schedule_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    schedule_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('Pending','Approved','Declined','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_appointments_students FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_schedule FOREIGN KEY (schedule_id) REFERENCES clinic_schedule(schedule_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS appointment_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    performed_by VARCHAR(100) NOT NULL,
    timestamp DATETIME NOT NULL,
    CONSTRAINT fk_logs_appointments FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed admin account
-- Password example: Admin123!
INSERT INTO users (role, email, password_hash, created_at)
SELECT 'admin', 'admin@feutech.edu.ph', '$2y$10$KX4VVy8eQ6Iu9bULd2kC0uY6j6Z4.0dN2KJvQF3f/0kY3B4Q3HfQ2', NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@feutech.edu.ph');

-- Seed sample schedule rows for the next few days.
INSERT INTO clinic_schedule (date, time_slot, is_available) VALUES
(CURDATE(), '09:00:00', 1),
(CURDATE(), '09:30:00', 1),
(CURDATE(), '10:00:00', 1),
(CURDATE(), '10:30:00', 1),
(CURDATE(), '11:00:00', 1),
(CURDATE(), '13:00:00', 1),
(CURDATE(), '13:30:00', 1),
(CURDATE(), '14:00:00', 1),
(CURDATE() + INTERVAL 1 DAY, '09:00:00', 1),
(CURDATE() + INTERVAL 1 DAY, '09:30:00', 1),
(CURDATE() + INTERVAL 1 DAY, '10:00:00', 1),
(CURDATE() + INTERVAL 1 DAY, '10:30:00', 1),
(CURDATE() + INTERVAL 1 DAY, '11:00:00', 1),
(CURDATE() + INTERVAL 2 DAY, '09:00:00', 1),
(CURDATE() + INTERVAL 2 DAY, '09:30:00', 1),
(CURDATE() + INTERVAL 2 DAY, '10:00:00', 1)
ON DUPLICATE KEY UPDATE is_available = VALUES(is_available);
