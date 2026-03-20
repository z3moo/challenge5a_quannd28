<?php

function db_getUserByUsername(string $username): array|false {
    $stmt = getDB()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function db_getUserById(int $id): array|false {
    $stmt = getDB()->prepare('SELECT id,username,full_name,email,phone,role,avatar,created_at FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function db_getUserRoleById(int $id): string|false {
    $stmt = getDB()->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? $row['role'] : false;
}

function db_getAllUsers(): array {
    return getDB()->query('SELECT id,username,full_name,email,phone,role,avatar,created_at FROM users ORDER BY role, full_name')->fetchAll();
}

function db_getUsersExcept(int $excludeId): array {
    $stmt = getDB()->prepare('SELECT id,username,full_name,email,phone,role,avatar,created_at FROM users WHERE id != ? ORDER BY role, full_name');
    $stmt->execute([$excludeId]);
    return $stmt->fetchAll();
}

function db_createUser(string $username, string $passwordHash, string $fullName, string $email, ?string $phone, ?string $avatar): void {
    getDB()->prepare('INSERT INTO users (username,password,full_name,email,phone,avatar,role) VALUES (?,?,?,?,?,?,?)')
           ->execute([$username, $passwordHash, $fullName, $email, $phone, $avatar, 'student']);
}

function db_updateUser(int $id, string $username, string $fullName, string $email, ?string $phone, ?string $avatar): void {
    getDB()->prepare('UPDATE users SET username=?,full_name=?,email=?,phone=?,avatar=? WHERE id=?')
           ->execute([$username, $fullName, $email, $phone, $avatar, $id]);
}

function db_updateUserWithPassword(int $id, string $username, string $fullName, string $email, ?string $phone, ?string $avatar, string $passwordHash): void {
    getDB()->prepare('UPDATE users SET username=?,full_name=?,email=?,phone=?,avatar=?,password=? WHERE id=?')
           ->execute([$username, $fullName, $email, $phone, $avatar, $passwordHash, $id]);
}

function db_deleteUser(int $id): void {
    getDB()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
}

function db_updateProfile(int $id, string $email, ?string $phone): void {
    getDB()->prepare('UPDATE users SET email=?, phone=? WHERE id=?')
           ->execute([$email, $phone, $id]);
}

function db_updateProfileWithPassword(int $id, string $email, ?string $phone, string $passwordHash): void {
    getDB()->prepare('UPDATE users SET email=?, phone=?, password=? WHERE id=?')
           ->execute([$email, $phone, $passwordHash, $id]);
}

function db_updateAvatar(int $id, string $avatar): void {
    getDB()->prepare('UPDATE users SET avatar=? WHERE id=?')
           ->execute([$avatar, $id]);
}

function db_getAssignments(): array {
    return getDB()->query("
        SELECT a.*, u.full_name as teacher_name
        FROM assignments a JOIN users u ON a.teacher_id = u.id
        ORDER BY a.created_at DESC
    ")->fetchAll();
}

function db_getAssignmentById(int $id): array|false {
    $stmt = getDB()->prepare('SELECT * FROM assignments WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function db_createAssignment(int $teacherId, string $title, string $desc, ?string $filePath): void {
    getDB()->prepare("INSERT INTO assignments (teacher_id,title,description,file_path) VALUES (?,?,?,?)")
           ->execute([$teacherId, $title, $desc, $filePath]);
}

function db_deleteAssignment(int $id, int $teacherId): void {
    getDB()->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?")->execute([$id, $teacherId]);
}

function db_getSubmissionsByStudent(int $studentId): array {
    $stmt = getDB()->prepare("SELECT assignment_id, file_path FROM submissions WHERE student_id = ?");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

function db_getAllSubmissionsWithUser(): array {
    return getDB()->query("
        SELECT s.*, u.full_name, u.email
        FROM submissions s JOIN users u ON s.student_id = u.id
        ORDER BY s.assignment_id, s.submitted_at DESC
    ")->fetchAll();
}

function db_upsertSubmission(int $assignmentId, int $studentId, string $filePath): void {
    getDB()->prepare("INSERT INTO submissions (assignment_id,student_id,file_path) VALUES (?,?,?) ON DUPLICATE KEY UPDATE file_path=?, submitted_at=NOW()")
           ->execute([$assignmentId, $studentId, $filePath, $filePath]);
}

function db_getChallenges(): array {
    return getDB()->query("
        SELECT c.*, u.full_name as teacher_name,
        (SELECT COUNT(*) FROM challenge_attempts ca WHERE ca.challenge_id=c.id AND ca.is_correct=1) as correct_count
        FROM challenges c JOIN users u ON c.teacher_id=u.id
        ORDER BY c.created_at DESC
    ")->fetchAll();
}

function db_getChallengeById(int $id): array|false {
    $stmt = getDB()->prepare("SELECT * FROM challenges WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function db_createChallenge(int $teacherId, string $title, ?string $hint, string $answerFilename, string $filePath): void {
    getDB()->prepare("INSERT INTO challenges (teacher_id,title,hint,answer_filename,file_path) VALUES (?,?,?,?,?)")
           ->execute([$teacherId, $title, $hint, $answerFilename, $filePath]);
}

function db_deleteChallenge(int $id, int $teacherId): void {
    getDB()->prepare("DELETE FROM challenges WHERE id = ? AND teacher_id = ?")->execute([$id, $teacherId]);
}

function db_getAllAttemptsWithUser(): array {
    return getDB()->query("
        SELECT ca.*, u.full_name
        FROM challenge_attempts ca JOIN users u ON ca.student_id=u.id
        ORDER BY ca.challenge_id, ca.id DESC
    ")->fetchAll();
}

function db_getAttemptsByStudent(int $studentId): array {
    $stmt = getDB()->prepare("SELECT * FROM challenge_attempts WHERE student_id=? ORDER BY id DESC");
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

function db_recordAttempt(int $challengeId, int $studentId, string $answer, bool $isCorrect): void {
    getDB()->prepare("INSERT INTO challenge_attempts (challenge_id,student_id,answer,is_correct) VALUES (?,?,?,?)")
           ->execute([$challengeId, $studentId, $answer, (int)$isCorrect]);
}

function db_sendMessage(int $fromId, int $toId, string $content): void {
    getDB()->prepare("INSERT INTO messages (sender_id,receiver_id,content) VALUES (?,?,?)")
           ->execute([$fromId, $toId, $content]);
}

function db_deleteMessage(int $id, int $senderId): void {
    getDB()->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?")->execute([$id, $senderId]);
}

function db_updateMessage(int $id, int $senderId, string $content): void {
    getDB()->prepare("UPDATE messages SET content = ? WHERE id = ? AND sender_id = ?")->execute([$content, $id, $senderId]);
}

function db_getChatMessages(int $userId, int $otherId): array {
    $stmt = getDB()->prepare("
        SELECT * FROM messages
        WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
        ORDER BY id ASC
    ");
    $stmt->execute([$userId, $otherId, $otherId, $userId]);
    return $stmt->fetchAll();
}
