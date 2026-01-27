/* =========================================================
   CMS Database (Aligned with your PDF Data Dictionary)
   - semester is VARCHAR(10) in students/subjects/attendance/exams/fees/results
   - users.email is VARCHAR(100)
   - payments.payment_date is DATE
   WARNING: Drops existing tables (data loss)
   ========================================================= */

SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS cms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cms;

/* ---------- DROP (order matters because of FKs) ---------- */
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS study_materials;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS marks;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS attendance_details;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS timetable;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS faculty_subject;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS faculty;
DROP TABLE IF EXISTS fees;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS users;

/* =========================================================
   USERS (Data Dictionary: email VARCHAR(100), role ENUM)
   ========================================================= */
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','faculty','student') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  session_token VARCHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

/* =========================================================
   COURSES
   ========================================================= */
CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_name VARCHAR(100) NOT NULL,
  duration INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (course_name)
) ENGINE=InnoDB;

/* =========================================================
   STUDENTS 
   ========================================================= */
CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE,
  name VARCHAR(100) NOT NULL,
  roll_number VARCHAR(50) NOT NULL UNIQUE,
  course_id INT NULL,
  semester VARCHAR(10) NULL,
  photo VARCHAR(255) NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(20) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_students_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_students_course
    FOREIGN KEY (course_id) REFERENCES courses(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_students_course_sem ON students(course_id, semester);

/* =========================================================
   FACULTY
   ========================================================= */
CREATE TABLE faculty (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NULL,
  department VARCHAR(100) NULL,
  phone VARCHAR(20) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_faculty_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

/* =========================================================
   SUBJECTS (Data Dictionary: semester VARCHAR(10))
   ========================================================= */
CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_name VARCHAR(100) NOT NULL,
  course_id INT NOT NULL,
  semester VARCHAR(10) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_subjects_course
    FOREIGN KEY (course_id) REFERENCES courses(id)
    ON DELETE CASCADE,
  UNIQUE (course_id, semester, subject_name)
) ENGINE=InnoDB;

CREATE INDEX idx_subjects_course_sem ON subjects(course_id, semester);

/* =========================================================
   FACULTY ↔ SUBJECT (Assignment)
   (Your PDF’s dictionary is missing subject_id here, but app needs it)
   ========================================================= */
CREATE TABLE faculty_subject (
  id INT AUTO_INCREMENT PRIMARY KEY,
  faculty_id INT NOT NULL,
  subject_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (faculty_id, subject_id),
  CONSTRAINT fk_facsub_faculty
    FOREIGN KEY (faculty_id) REFERENCES faculty(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_facsub_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

/* =========================================================
   ENROLLMENTS (STUDENT ↔ SUBJECT)
   ========================================================= */
CREATE TABLE enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (student_id, subject_id),
  CONSTRAINT fk_enroll_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_enroll_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_enrollments_subject ON enrollments(subject_id);

/* =========================================================
   TIMETABLE / CLASS ROUTINE (YT feature)
   semester as VARCHAR(10) to stay consistent with your system
   ========================================================= */
CREATE TABLE timetable (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  semester VARCHAR(10) NOT NULL,
  subject_id INT NOT NULL,
  faculty_id INT NULL,
  day_of_week ENUM('Sun','Mon','Tue','Wed','Thu','Fri','Sat') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  room VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tt_course
    FOREIGN KEY (course_id) REFERENCES courses(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_tt_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_tt_faculty
    FOREIGN KEY (faculty_id) REFERENCES faculty(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_tt_course_sem_day ON timetable(course_id, semester, day_of_week);

/* =========================================================
   ATTENDANCE (Data Dictionary: semester VARCHAR(10))
   ========================================================= */
CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  course_id INT NOT NULL,
  semester VARCHAR(10) NOT NULL,
  date DATE NOT NULL,
  created_by_faculty_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_att_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_att_course
    FOREIGN KEY (course_id) REFERENCES courses(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_att_createdby
    FOREIGN KEY (created_by_faculty_id) REFERENCES faculty(id)
    ON DELETE SET NULL,
  UNIQUE (subject_id, course_id, semester, date)
) ENGINE=InnoDB;

CREATE INDEX idx_att_course_sem_date ON attendance(course_id, semester, date);

CREATE TABLE attendance_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attendance_id INT NOT NULL,
  student_id INT NOT NULL,
  status ENUM('present','absent') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (attendance_id, student_id),
  CONSTRAINT fk_attd_att
    FOREIGN KEY (attendance_id) REFERENCES attendance(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_attd_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_attd_student ON attendance_details(student_id);

/* =========================================================
   EXAMS (Data Dictionary: semester VARCHAR(10))
   ========================================================= */
CREATE TABLE exams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_name VARCHAR(100) NOT NULL,
  course_id INT NOT NULL,
  semester VARCHAR(10) NOT NULL,
  exam_date DATE NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_exams_course
    FOREIGN KEY (course_id) REFERENCES courses(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_exams_course_sem ON exams(course_id, semester);

/* =========================================================
   MARKS
   ========================================================= */
CREATE TABLE marks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  marks INT NULL,
  entered_by_faculty_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (exam_id, student_id, subject_id),
  CONSTRAINT fk_marks_exam
    FOREIGN KEY (exam_id) REFERENCES exams(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_marks_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_marks_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_marks_enteredby
    FOREIGN KEY (entered_by_faculty_id) REFERENCES faculty(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_marks_student_exam ON marks(student_id, exam_id);

/* =========================================================
   RESULTS (PDF includes results in scope; keep it)
   semester VARCHAR(10) for consistency
   ========================================================= */
CREATE TABLE results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  semester VARCHAR(10) NOT NULL,
  gpa DECIMAL(4,2) NULL,
  cgpa DECIMAL(4,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (student_id, semester),
  CONSTRAINT fk_results_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

/* =========================================================
   FEES (Data Dictionary: semester VARCHAR(10))
   ========================================================= */
CREATE TABLE fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  semester VARCHAR(10) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (course_id, semester),
  CONSTRAINT fk_fees_course
    FOREIGN KEY (course_id) REFERENCES courses(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

/* =========================================================
   INVOICES (extra, useful for online payment workflow)
   ========================================================= */
CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(40) NOT NULL UNIQUE,
  student_id INT NOT NULL,
  fee_id INT NOT NULL,
  amount_due DECIMAL(10,2) NOT NULL,
  due_date DATE NULL,
  status ENUM('unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inv_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_inv_fee
    FOREIGN KEY (fee_id) REFERENCES fees(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_invoices_student_status ON invoices(student_id, status);

/* =========================================================
   PAYMENTS (Data Dictionary: status Paid/Pending, payment_date DATE)
   For code simplicity we keep lowercase values; UI can show labels.
   ========================================================= */
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NULL,
  student_id INT NOT NULL,
  fee_id INT NOT NULL,
  status ENUM('paid','pending') NOT NULL DEFAULT 'paid',
  payment_date DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_invoice
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_pay_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_pay_fee
    FOREIGN KEY (fee_id) REFERENCES fees(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_payments_student_date ON payments(student_id, payment_date);

/* =========================================================
   STUDY MATERIALS (YT feature)
   ========================================================= */
CREATE TABLE study_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  course_id INT NOT NULL,
  semester VARCHAR(10) NOT NULL,
  uploaded_by_faculty_id INT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mat_subject
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_mat_course
    FOREIGN KEY (course_id) REFERENCES courses(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_mat_faculty
    FOREIGN KEY (uploaded_by_faculty_id) REFERENCES faculty(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_materials_course_sem ON study_materials(course_id, semester);

/* =========================================================
   MESSAGES (YT feature: messaging)
   ========================================================= */
CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_user_id INT NOT NULL,
  receiver_user_id INT NOT NULL,
  subject VARCHAR(150) NULL,
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_sender
    FOREIGN KEY (sender_user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver
    FOREIGN KEY (receiver_user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_messages_inbox ON messages(receiver_user_id, is_read, created_at);

/* =========================================================
   NOTIFICATIONS (Data Dictionary: message TEXT)
   ========================================================= */
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_notifications_user ON notifications(user_id, created_at);

/* =========================================================
   SYSTEM SETTINGS (extra)
   ========================================================= */
CREATE TABLE system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

/* =========================================================
   AUDIT LOGS (Tracking admin actions)
   ========================================================= */
CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  table_name VARCHAR(100) NOT NULL,
  record_id INT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_audit_user_date ON audit_logs(user_id, created_at);
CREATE INDEX idx_audit_table ON audit_logs(table_name, created_at);

INSERT INTO system_settings (setting_key, setting_value)
VALUES
  ('college_name', 'Your College Name'),
  ('academic_year', '2025-2026'),
  ('default_language', 'en')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
