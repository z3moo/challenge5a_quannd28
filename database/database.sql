SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci';

CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    phone      VARCHAR(20)  NULL,
    role       ENUM('teacher','student') NOT NULL DEFAULT 'student',
    avatar     VARCHAR(500) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS assignments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id  INT NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT NULL,
    file_path   VARCHAR(500) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS submissions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id    INT NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_submission (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)    REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    content     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS challenges (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id      INT NOT NULL,
    title           VARCHAR(200) NOT NULL,
    hint            TEXT NULL,
    answer_filename VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(500) NULL DEFAULT NULL AFTER phone;

ALTER TABLE challenges ADD COLUMN IF NOT EXISTS hint TEXT NULL AFTER title;

CREATE TABLE IF NOT EXISTS challenge_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    student_id   INT NOT NULL,
    answer       VARCHAR(255) NOT NULL,
    is_correct   TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)   REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE challenge_attempts
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

INSERT INTO users (username, password, full_name, email, phone, role) VALUES
('teacher1', '$2y$10$GZbz9gzJnrDIRdtUHr2jQ.wRcxUw46LZYP1GjQ9/uhKlNTCLO3nQu', 'Teacher 1', 'teacher1@example.invalid', NULL, 'teacher'),
('teacher2', '$2y$10$Vwjx.u1GldlTNWYy/Vox8u3ORXneqK//K2xepgcysABfawU8AhvKK', 'Teacher 2', 'teacher2@example.invalid', NULL, 'teacher'),
('student1', '$2y$10$pav7bx8/XpRPV2yipJ0GLOjdd6J5n3bHP0j12QMOmspHlNcp88jay', 'Student 1', 'student1@example.invalid', NULL, 'student'),
('student2', '$2y$10$nIV.4syDjKJagkNTmJAD9O.TRCt82XbjBs9dgaa6wriI0wg95dypG', 'Student 2', 'student2@example.invalid', NULL, 'student');