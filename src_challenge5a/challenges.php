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
        $file = $_FILES['file'] ?? null;

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($ext !== 'txt') {
                flash('error', 'Chỉ chấp nhận file .txt!');
                redirect(BASE_URL . '/challenges.php');
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                flash('error', 'File quá lớn. Tối đa 10 MB.');
                redirect(BASE_URL . '/challenges.php');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mime !== 'text/plain') {
                flash('error', 'File không hợp lệ.');
                redirect(BASE_URL . '/challenges.php');
            }

            $dir = UPLOAD_DIR . 'challenges/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $savedName = bin2hex(random_bytes(16)) . '.txt';
            move_uploaded_file($file['tmp_name'], $dir . $savedName);

            $hint = trim($_POST['hint'] ?? '');
            db_createChallenge($me['id'], $title, $hint ?: null, $originalName, 'challenges/' . $savedName);
            flash('success', 'Đã tạo challenge! Đáp án là tên file: "' . $originalName . '"');
        } else {
            flash('error', 'Vui lòng upload file .txt nội dung.');
        }
        redirect(BASE_URL . '/challenges.php');
    }

    if ($action === 'delete' && $me['role'] === 'teacher') {
        db_deleteChallenge((int)$_POST['id'], $me['id']);
        flash('success', 'Đã xóa challenge.');
        redirect(BASE_URL . '/challenges.php');
    }

    if ($action === 'answer' && $me['role'] === 'student') {
        $cid = (int)$_POST['challenge_id'];
        $answer = trim($_POST['answer']);

        $rateKey = 'ch_rate_' . $cid;
        $now = time();
        if (!isset($_SESSION[$rateKey]) || $_SESSION[$rateKey]['reset'] <= $now) {
            $_SESSION[$rateKey] = ['count' => 0, 'reset' => $now + 300];
        }
        $_SESSION[$rateKey]['count']++;
        if ($_SESSION[$rateKey]['count'] > 10) {
            $wait = $_SESSION[$rateKey]['reset'] - $now;
            flash('error', 'Quá nhiều lần thử. Vui lòng đợi ' . $wait . ' giây.');
            redirect(BASE_URL . '/challenges.php?open=' . $cid);
        }

        $challenge = db_getChallengeById($cid);

        if ($challenge) {
            $isCorrect = (strtolower($answer) === strtolower($challenge['answer_filename']));
            db_recordAttempt($cid, $me['id'], $answer, $isCorrect);

            if ($isCorrect) {
                $filePath = UPLOAD_DIR . $challenge['file_path'];
                $content = file_exists($filePath) ? file_get_contents($filePath) : '';
                $_SESSION['challenge_win'] = ['id' => $cid, 'content' => $content];
            }
        }
        redirect(BASE_URL . '/challenges.php?open=' . $cid);
    }
}

$challenges = db_getChallenges();

$attemptsMap = [];
if ($me['role'] === 'teacher') {
    foreach (db_getAllAttemptsWithUser() as $r) {
        $attemptsMap[$r['challenge_id']][] = $r;
    }
}

$myAttemptsMap = [];
if ($me['role'] === 'student') {
    foreach (db_getAttemptsByStudent($me['id']) as $r) {
        $myAttemptsMap[$r['challenge_id']][] = $r;
    }
}

$winContent = null;
$winChallengeId = null;
if (isset($_SESSION['challenge_win'])) {
    $winChallengeId = (int)$_SESSION['challenge_win']['id'];
    $winContent     = $_SESSION['challenge_win']['content'];
    unset($_SESSION['challenge_win']);
}

renderHeader('Challenge');
?>

<div class="page-header flex justify-between items-center">
    <div class="page-header-text">
        <h2>Challenge</h2>
        <p>Đoán tên file đáp án để xem nội dung bài thơ / văn bí ẩn</p>
    </div>
    <?php if ($me['role'] === 'teacher'): ?>
    <button class="btn btn-primary" onclick="toggleForm()">Tạo Challenge</button>
    <?php endif; ?>
</div>

<?php if ($me['role'] === 'teacher'): ?>
<div id="addForm" class="card mb-4" style="display:none">
    <div class="card-header"><h3>Tạo Challenge mới</h3></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="form-group">
                <label>Tiêu đề challenge *</label>
                <input type="text" name="title" placeholder="VD: Đoán tên bài thơ nổi tiếng" required>
            </div>
            <div class="form-group">
                <label>Gợi ý <span class="text-muted">(tuỳ chọn — hiển thị cho sinh viên)</span></label>
                <input type="text" name="hint" placeholder="VD: Đây là tên một bài thơ của Xuân Diệu">
            </div>
            <div class="form-group">
                <label>File nội dung (.txt) * — Tên file = Đáp án</label>
                <input type="file" name="file" accept=".txt" required>
            </div>
            <button type="submit" class="btn btn-primary">Tạo Challenge</button>
            <button type="button" class="btn btn-outline" onclick="toggleForm()">Hủy</button>
        </form>
    </div>
</div>
<?php endif; ?>



<div class="card">
    <div class="card-header"><h3>Danh sách Challenge (<?= count($challenges) ?>)</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Challenge</th><th>Giáo viên</th><th>Giải đúng</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($challenges as $c): ?>
            <tr>
                <td><strong><?= h($c['title']) ?></strong></td>
                <td><?= h($c['teacher_name']) ?></td>
                <td><span class="badge badge-green"><?= $c['correct_count'] ?> người</span></td>
                <td class="text-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <?php if ($me['role'] === 'teacher'): ?>
                    <?php $attCount = count($attemptsMap[$c['id']] ?? []); ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleChallenge(<?= $c['id'] ?>)">
                        Chi tiết<?= $attCount ? ' ('.$attCount.')' : '' ?>
                    </button>
                    <?php else: ?>
                    <?php $myAtts0 = $myAttemptsMap[$c['id']] ?? []; $studentWon = !empty(array_filter($myAtts0, fn($a) => $a['is_correct'])); ?>
                    <button type="button" class="btn <?= $studentWon ? 'btn-outline' : 'btn-primary' ?> btn-sm" onclick="toggleChallenge(<?= $c['id'] ?>)">
                        <?= $studentWon ? 'Xem lại' : 'Vào Challenge' ?>
                    </button>
                    <?php endif; ?>
                    <?php if ($me['role'] === 'teacher' && $c['teacher_id'] == $me['id']): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Xóa challenge này?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <button class="btn btn-danger btn-sm">Xóa</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($me['role'] === 'teacher'): ?>
            <tr id="ch-row-<?= $c['id'] ?>" style="display:none">
                <td colspan="5" style="padding:0;border-top:none">
                    <div style="background:#f7f9fc;border-top:2px solid #e0e7ef;padding:12px 16px">
                        <div class="alert alert-info" style="margin-bottom:12px">Đáp án: <strong><?= h($c['answer_filename']) ?></strong></div>
                        <?php $atts = $attemptsMap[$c['id']] ?? []; ?>
                        <?php if (empty($atts)): ?>
                            <span class="text-muted">Chưa có sinh viên nào tham gia.</span>
                        <?php else: ?>
                        <table style="margin:0">
                            <thead><tr><th>Sinh viên</th><th>Đáp án</th><th>Kết quả</th><th>Thời gian</th></tr></thead>
                            <tbody>
                            <?php foreach ($atts as $att): ?>
                            <tr>
                                <td><?= h($att['full_name']) ?></td>
                                <td><?= h($att['answer']) ?></td>
                                <td><span class="badge <?= $att['is_correct']?'badge-green':'badge-red' ?>"><?= $att['is_correct']?'Đúng':'Sai' ?></span></td>
                                <td class="text-muted"><?= !empty($att['created_at']) ? date('H:i d/m', strtotime($att['created_at'])) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($me['role'] === 'student'): ?>
            <tr id="ch-row-<?= $c['id'] ?>" style="display:none">
                <td colspan="5" style="padding:0;border-top:none">
                    <div style="background:#f7f9fc;border-top:2px solid #005fb8;padding:12px 16px">
                        <?php if (!empty($c['hint'])): ?>
                        <div class="alert alert-info" style="margin-bottom:12px"><strong>Gợi ý:</strong> <?= h($c['hint']) ?></div>
                        <?php endif; ?>
                        <?php
                        $myAtts    = $myAttemptsMap[$c['id']] ?? [];
                        $alreadyWon = !empty(array_filter($myAtts, fn($a) => $a['is_correct']));
                        $isJustWon  = ($winChallengeId === (int)$c['id']);
                        ?>
                        <?php if ($isJustWon && $winContent): ?>
                        <div class="alert alert-success"><strong>Chính xác!</strong> Nội dung bài thơ/văn:<br><br>
                            <pre style="background:#fafafa;padding:14px;border-radius:4px;white-space:pre-wrap;line-height:1.8"><?= h($winContent) ?></pre>
                        </div>
                        <?php elseif ($alreadyWon): ?>
                        <?php $content = file_exists(UPLOAD_DIR . $c['file_path']) ? file_get_contents(UPLOAD_DIR . $c['file_path']) : ''; ?>
                        <div class="alert alert-success" style="margin-bottom:10px">Bạn đã giải đúng challenge này!</div>
                        <pre style="background:#fafafa;padding:14px;border-radius:4px;white-space:pre-wrap;line-height:1.8"><?= h($content) ?></pre>
                        <?php else: ?>
                        <div class="card" style="border:2px dashed #005fb8;max-width:500px;margin-bottom:12px">
                            <div class="card-body">
                                <p style="margin-bottom:12px">Hãy đoán tên bài thơ (viết chữ thường, không dấu)</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="answer">
                                    <input type="hidden" name="challenge_id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <div style="display:flex;gap:8px">
                                        <input type="text" name="answer" placeholder="Nhập đáp án..." required>
                                        <button type="submit" class="btn btn-primary">Gửi</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($myAtts): ?>
                        <div class="mt-4">
                            <h4 style="font-size:.9rem;margin-bottom:8px">Lịch sử trả lời của bạn</h4>
                            <?php foreach ($myAtts as $att): ?>
                            <div style="padding:6px 10px;border-radius:6px;background:<?= $att['is_correct']?'#d1fae5':'#fee2e2' ?>;margin-bottom:4px;font-size:.83rem">
                                <?= $att['is_correct']?'Đúng':'Sai' ?> <strong><?= h($att['answer']) ?></strong>
                                <span class="text-muted"> · <?= !empty($att['created_at']) ? date('H:i d/m', strtotime($att['created_at'])) : '' ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty($challenges)): ?>
            <tr><td colspan="5" class="text-muted" style="text-align:center;padding:30px">Chưa có challenge nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleForm() {
    const f = document.getElementById('addForm');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
function toggleChallenge(id) {
    const row = document.getElementById('ch-row-' + id);
    if (!row) return;
    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
(function() {
    const openId = new URLSearchParams(window.location.search).get('open');
    if (openId) {
        const row = document.getElementById('ch-row-' + openId);
        if (row) { row.style.display = 'table-row'; row.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
    }
})();
</script>

<?php renderFooter(); ?>
