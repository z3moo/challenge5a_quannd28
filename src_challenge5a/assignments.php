<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/database.php';
requireLogin();

$me = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && $me['role'] === 'teacher') {
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $file = uploadFile('file', 'assignments', ALLOWED_DOC_TYPES);
        db_createAssignment($me['id'], $title, $desc, $file);
        flash('success', 'Đã thêm bài tập!');
        redirect(BASE_URL . '/assignments.php');
    }

    if ($action === 'delete' && $me['role'] === 'teacher') {
        db_deleteAssignment((int)$_POST['id'], $me['id']);
        flash('success', 'Đã xóa bài tập.');
        redirect(BASE_URL . '/assignments.php');
    }

    if ($action === 'submit' && $me['role'] === 'student') {
        $aid = (int)$_POST['assignment_id'];
        if (!db_getAssignmentById($aid)) {
            flash('error', 'Bài tập không tồn tại.');
            redirect(BASE_URL . '/assignments.php');
        }
        $file = uploadFile('file', 'submissions', array_merge(ALLOWED_DOC_TYPES, ALLOWED_IMG_TYPES));
        if ($file) {
            try {
                db_upsertSubmission($aid, $me['id'], $file);
                flash('success', 'Nộp bài thành công!');
            } catch (PDOException $e) {
                flash('error', 'Lỗi khi nộp bài.');
            }
        } else {
            flash('error', 'Vui lòng chọn file hợp lệ.');
        }
        redirect(BASE_URL . '/assignments.php');
    }
}

$assignments = db_getAssignments();

$submitted = [];
if ($me['role'] === 'student') {
    foreach (db_getSubmissionsByStudent($me['id']) as $r) {
        $submitted[$r['assignment_id']] = $r['file_path'];
    }
}

$subsMap = [];
if ($me['role'] === 'teacher') {
    foreach (db_getAllSubmissionsWithUser() as $r) {
        $subsMap[$r['assignment_id']][] = $r;
    }
}

renderHeader('Bài tập');
?>

<div class="page-header flex justify-between items-center">
    <div class="page-header-text">
        <h2>Bài tập</h2>
        <p><?= $me['role']==='teacher' ? 'Quản lý và giao bài tập cho sinh viên' : 'Danh sách bài tập cần hoàn thành' ?></p>
    </div>
    <?php if ($me['role'] === 'teacher'): ?>
    <button class="btn btn-primary" onclick="toggleForm()">Giao bài tập</button>
    <?php endif; ?>
</div>

<?php if ($me['role'] === 'teacher'): ?>
<div id="addForm" class="card mb-4" style="display:none">
    <div class="card-header"><h3>Giao bài tập mới</h3></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="form-group">
                <label>Tiêu đề bài tập *</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Mô tả / Yêu cầu</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>File đính kèm (PDF, DOC, ZIP, TXT)</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.zip,.txt">
            </div>
            <button type="submit" class="btn btn-primary">Giao bài</button>
            <button type="button" class="btn btn-outline" onclick="toggleForm()">Hủy</button>
        </form>
    </div>
</div>
<?php endif; ?>



<div class="card">
    <div class="card-header"><h3>Tất cả bài tập (<?= count($assignments) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Bài tập</th>
                    <th>Giáo viên</th>
                    <th>File</th>
                    <th>Ngày giao</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
            <tr>
                <td>
                    <strong><?= h($a['title']) ?></strong>
                    <?php if ($a['description']): ?>
                    <div class="text-muted text-sm"><?= nl2br(h(substr($a['description'],0,80))) ?>...</div>
                    <?php endif; ?>
                </td>
                <td><?= h($a['teacher_name']) ?></td>
                <td>
                    <?php if ($a['file_path']): ?>
                    <a href="<?= BASE_URL.'/file.php?path='.urlencode($a['file_path']) ?>" class="btn btn-outline btn-sm">Tải về</a>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="text-muted"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
                <td>
                    <?php if ($me['role'] === 'teacher'): ?>
                        <?php $subCount = count($subsMap[$a['id']] ?? []); ?>
                        <button type="button" class="btn btn-outline btn-sm" onclick="toggleSubs(<?= $a['id'] ?>)">
                            Bài nộp<?= $subCount ? ' ('.$subCount.')' : '' ?>
                        </button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Xóa bài tập này?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button class="btn btn-danger btn-sm">Xóa</button>
                        </form>
                    <?php else: ?>
                        <?php if (isset($submitted[$a['id']])): ?>
                            <span class="badge badge-green">Đã nộp</span>
                        <?php else: ?>
                            <button class="btn btn-primary btn-sm" onclick="showSubmit(<?= $a['id'] ?>)">Nộp bài</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($me['role'] === 'teacher'): ?>
            <tr id="subs-row-<?= $a['id'] ?>" class="subs-dropdown-row" style="display:none">
                <td colspan="5" style="padding:0;border-top:none">
                    <div style="background:#f7f9fc;border-top:2px solid #e0e7ef;padding:12px 16px">
                        <?php $subs = $subsMap[$a['id']] ?? []; ?>
                        <?php if (empty($subs)): ?>
                            <span class="text-muted">Chưa có sinh viên nào nộp bài.</span>
                        <?php else: ?>
                        <table style="margin:0">
                            <thead><tr><th>Sinh viên</th><th>Email</th><th>File nộp</th><th>Thời gian</th></tr></thead>
                            <tbody>
                            <?php foreach ($subs as $s): ?>
                            <tr>
                                <td><strong><?= h($s['full_name']) ?></strong></td>
                                <td><?= h($s['email']) ?></td>
                                <td><a href="<?= BASE_URL.'/file.php?path='.urlencode($s['file_path']) ?>" class="btn btn-outline btn-sm">Tải xuống</a></td>
                                <td class="text-muted"><?= date('d/m/Y H:i', strtotime($s['submitted_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty($assignments)): ?>
            <tr><td colspan="5" class="text-muted" style="text-align:center;padding:30px">Chưa có bài tập nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="submitModal" class="modal-overlay">
    <div class="card" style="width:400px;max-width:90%">
        <div class="card-header"><h3>Nộp bài</h3></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="assignment_id" id="submitAid">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="form-group">
                    <label>Chọn file bài làm</label>
                    <input type="file" name="file" required accept=".pdf,.doc,.docx,.zip,.txt,.png,.jpg">
                </div>
                <div style="display:flex;gap:8px">
                    <button type="submit" class="btn btn-primary">Nộp bài</button>
                    <button type="button" class="btn btn-outline" onclick="closeSubmit()">Hủy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleForm() {
    const f = document.getElementById('addForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
function toggleSubs(id) {
    const row = document.getElementById('subs-row-' + id);
    if (!row) return;
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
function showSubmit(id) {
    document.getElementById('submitAid').value = id;
    document.getElementById('submitModal').classList.add('show');
}
function closeSubmit() {
    document.getElementById('submitModal').classList.remove('show');
}
</script>

<?php renderFooter(); ?>
