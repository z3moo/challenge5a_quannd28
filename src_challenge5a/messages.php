<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/database.php';
requireLogin();

$me = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();
    if ($_POST['action'] === 'send') {
        $to = (int)$_POST['receiver_id'];
        $content = trim($_POST['content']);
        if ($content && $to && $to !== $me['id']) {
            db_sendMessage($me['id'], $to, $content);
        }
        redirect(BASE_URL . '/messages.php?to=' . $to);
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        db_deleteMessage($id, $me['id']);
        $to = (int)$_POST['to'];
        redirect(BASE_URL . '/messages.php?to=' . $to);
    }
    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $content = trim($_POST['content']);
        if ($content) {
            db_updateMessage($id, $me['id'], $content);
        }
        redirect(BASE_URL . '/messages.php?to=' . (int)$_POST['to']);
    }
}

$chatWith = null;
$messages = [];
if (isset($_GET['to'])) {
    $chatWith = db_getUserById((int)$_GET['to']);

    if ($chatWith) {
        $messages = db_getChatMessages($me['id'], $chatWith['id']);
    }
}

$users = db_getUsersExcept($me['id']);

renderHeader('Tin nhắn');
?>
<style>
.msg-layout { display: grid; grid-template-columns: 220px 1fr; gap: 15px; height: calc(100vh - 150px); }
.user-list { background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow-y: auto; }
.user-list-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #eee;
    text-decoration: none; color: #333;
    transition: background .15s;
}
.user-list-item:hover, .user-list-item.active { background: #e5f1fb; }
.chat-box { background: #fff; border: 1px solid #ddd; border-radius: 6px; display: flex; flex-direction: column; }
.chat-header { padding: 12px 15px; border-bottom: 1px solid #ddd; display: flex; align-items: center; gap: 10px; }
.messages-area { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 8px; }
.msg { max-width: 70%; }
.msg-me { align-self: flex-end; }
.msg-other { align-self: flex-start; }
.msg-bubble {
    padding: 8px 12px; border-radius: 10px;
    font-size: 13px; line-height: 1.5;
}
.msg-me .msg-bubble { background: #005fb8; color: #fff; border-bottom-right-radius: 3px; }
.msg-other .msg-bubble { background: #f0f0f0; color: #333; border-bottom-left-radius: 3px; }
.msg-time { font-size: 11px; color: #888; margin-top: 3px; }
.msg-me .msg-time { text-align: right; }
.msg-actions { font-size: 11px; margin-top: 3px; }
.msg-actions a { color: #888; cursor: pointer; margin-right: 8px; text-decoration: none; }
.msg-actions a:hover { color: #d13438; }
.chat-input { padding: 12px; border-top: 1px solid #ddd; display: flex; gap: 8px; }
.chat-input input { flex: 1; }
.no-chat { display: flex; align-items: center; justify-content: center; height: 100%; color: #888; flex-direction: column; gap: 8px; }
@media (max-width: 700px) {
    .msg-layout { grid-template-columns: 1fr; height: auto; }
    .user-list { max-height: 200px; }
}
.modal-overlay.show { display: flex; }
</style>

<div class="page-header">
    <div class="page-header-text">
        <h2>Tin nhắn</h2>
        <p>Nhắn tin với giáo viên và sinh viên trong lớp</p>
    </div>
</div>

<div class="msg-layout">
    <div class="user-list">
        <?php foreach ($users as $u): ?>
        <a href="?to=<?= $u['id'] ?>" class="user-list-item <?= ($chatWith && $chatWith['id']==$u['id']) ? 'active' : '' ?>">
            <div>
                <div style="font-size:13px;font-weight:600"><?= h($u['full_name']) ?></div>
                <div style="font-size:11px;color:#888"><?= $u['role']==='teacher'?'Giáo viên':'Sinh viên' ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="chat-box">
        <?php if ($chatWith): ?>
        <div class="chat-header">
            <div>
                <strong><?= h($chatWith['full_name']) ?></strong>
                <div style="font-size:11px;color:#888"><?= $chatWith['role']==='teacher'?'Giáo viên':'Sinh viên' ?></div>
            </div>
        </div>
        <div class="messages-area" id="msgArea">
            <?php if (empty($messages)): ?>
            <div class="no-chat">Hãy bắt đầu cuộc trò chuyện!</div>
            <?php endif; ?>
            <?php foreach ($messages as $msg): ?>
            <?php $isMe = $msg['sender_id'] == $me['id']; ?>
            <div class="msg <?= $isMe ? 'msg-me' : 'msg-other' ?>" id="msg-<?= $msg['id'] ?>">
                <div class="msg-bubble"><?= nl2br(h($msg['content'])) ?></div>
                <div class="msg-time"><?= date('H:i d/m', strtotime($msg['created_at'] ?? $msg['sent_at'] ?? 'now')) ?></div>
                <?php if ($isMe): ?>
                <div class="msg-actions">
                    <a data-id="<?= $msg['id'] ?>" data-content="<?= h($msg['content']) ?>" onclick="editMsg(this)">Sửa</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Xóa tin nhắn?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                        <input type="hidden" name="to" value="<?= $chatWith['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <a onclick="this.closest('form').submit()">Xóa</a>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="chat-input">
            <form method="POST" style="display:flex;gap:8px;width:100%" id="sendForm">
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="receiver_id" value="<?= $chatWith['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="text" name="content" id="msgInput" placeholder="Nhập tin nhắn..." autocomplete="off" required>
                <button type="submit" class="btn btn-primary">Gửi</button>
            </form>
        </div>

        <div id="editModal" class="modal-overlay">
            <div class="card" style="width:400px;max-width:90%">
                <div class="card-header"><h3>Sửa tin nhắn</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="editId">
                        <input type="hidden" name="to" value="<?= $chatWith['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <div class="form-group">
                            <textarea name="content" id="editContent" rows="3" required></textarea>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="submit" class="btn btn-primary">Lưu</button>
                            <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('show')">Hủy</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="no-chat">
            <p>Chọn người dùng bên trái để bắt đầu nhắn tin</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const area = document.getElementById('msgArea');
if (area) area.scrollTop = area.scrollHeight;

function editMsg(el) {
    document.getElementById('editId').value = el.dataset.id;
    document.getElementById('editContent').value = el.dataset.content;
    document.getElementById('editModal').classList.add('show');
}
</script>

<?php renderFooter(); ?>
