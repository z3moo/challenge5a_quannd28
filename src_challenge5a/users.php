<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/database.php';
requireLogin();
$me   = currentUser();
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    requireTeacher();
    verifyCsrf();
    $fields = ['username','password','full_name','email','phone','role'];
    $data   = array_map('trim', array_intersect_key($_POST, array_flip($fields)));
    $avatar = null;
    if (!empty($_FILES['avatar_file']) && ($_FILES['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $avatar = uploadFile('avatar_file', 'avatars', ALLOWED_IMG_TYPES);
        if (!$avatar) {
            flash('error', 'Avatar không hợp lệ. Hỗ trợ PNG, JPG, GIF.');
            redirect(BASE_URL . '/users.php');
        }
    } elseif (!empty($_POST['avatar_url'])) {
        $url = trim($_POST['avatar_url']);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
            flash('error', 'URL avatar không hợp lệ.');
            redirect(BASE_URL . '/users.php');
        }
        $avatar = $url;
    }
    if (empty($data['username']) || empty($data['password']) || empty($data['full_name']) || empty($data['email'])) {
        flash('error', 'Vui lòng điền đầy đủ thông tin bắt buộc.');
    } else {
        try {
            db_createUser($data['username'], password_hash($data['password'], PASSWORD_DEFAULT),
                          $data['full_name'], $data['email'], $data['phone'] ?: null, $avatar);
            flash('success', 'Thêm người dùng thành công!');
        } catch (PDOException $e) {
            flash('error', 'Tên đăng nhập hoặc email đã tồn tại.');
        }
    }
    redirect(BASE_URL . '/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id) {
    requireTeacher();
    verifyCsrf();
    $targetUser = db_getUserById($id);
    if (!$targetUser) { flash('error', 'Không tìm thấy người dùng.'); redirect(BASE_URL . '/users.php'); }
    if ($targetUser['role'] === 'teacher' && (int)$me['id'] !== $id) {
        flash('error', 'Không có quyền chỉnh sửa tài khoản giáo viên khác.');
        redirect(BASE_URL . '/users.php');
    }
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '') ?: null;
    $avatar    = $targetUser['avatar'] ?? null;
    if (!empty($_POST['remove_avatar'])) {
        $avatar = null;
    }
    if (!empty($_FILES['avatar_file']) && ($_FILES['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadedAvatar = uploadFile('avatar_file', 'avatars', ALLOWED_IMG_TYPES);
        if (!$uploadedAvatar) {
            flash('error', 'Avatar không hợp lệ. Hỗ trợ PNG, JPG, GIF.');
            redirect(BASE_URL . '/users.php?action=edit&id=' . $id);
        }
        $avatar = $uploadedAvatar;
    } elseif (!empty($_POST['avatar_url'])) {
        $url = trim($_POST['avatar_url']);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
            flash('error', 'URL avatar không hợp lệ.');
            redirect(BASE_URL . '/users.php?action=edit&id=' . $id);
        }
        $avatar = $url;
    }
    $newpass   = $_POST['new_password'] ?? '';
    if (empty($username) || empty($full_name) || empty($email)) {
        flash('error', 'Vui lòng điền đầy đủ thông tin bắt buộc.');
        redirect(BASE_URL . '/users.php?action=edit&id=' . $id);
    }
    try {
        if ($newpass) {
            db_updateUserWithPassword($id, $username, $full_name, $email, $phone, $avatar, password_hash($newpass, PASSWORD_DEFAULT));
        } else {
            db_updateUser($id, $username, $full_name, $email, $phone, $avatar);
        }
        flash('success', 'Cập nhật thành công!');
    } catch (PDOException $e) {
        flash('error', 'Tên đăng nhập hoặc email đã được dùng bởi tài khoản khác.');
    }
    redirect(BASE_URL . '/users.php?action=view&id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $id) {
    requireTeacher();
    verifyCsrf();
    if ($id === (int)$me['id']) { flash('error', 'Không thể xóa chính mình.'); }
    else {
        $delRole = db_getUserRoleById($id);
        if ($delRole === 'teacher') {
            flash('error', 'Không có quyền xóa tài khoản giáo viên.');
        } else {
            db_deleteUser($id);
            flash('success', 'Đã xóa người dùng.');
        }
    }
    redirect(BASE_URL . '/users.php');
}

if ($action === 'view' && $id) {
    $user = db_getUserById($id);
    if (!$user) redirect(BASE_URL . '/users.php');
    renderHeader('Chi tiết người dùng'); ?>
    <div class="page-header">
        <div class="page-header-text">
            <h2>Thông tin người dùng</h2>
        </div>
        <div class="flex gap-2">
            <?php if (isTeacher() && ($user['role'] !== 'teacher' || $user['id'] == $me['id'])): ?>
            <a href="<?= BASE_URL ?>/users.php?action=edit&id=<?= $user['id'] ?>" class="btn btn-outline">Sửa</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/users.php" class="btn btn-outline">Quay lại</a>
        </div>
    </div>
    <div class="card" style="max-width:480px">
        <div class="card-body">
            <table style="width:100%">
                <?php if (!empty($user['avatar'])): ?>
                <?php $avatarSrc = preg_match('/^https?:\/\//i', $user['avatar']) ? $user['avatar'] : BASE_URL . '/file.php?path=' . urlencode($user['avatar']); ?>
                <tr>
                    <td class="text-muted" style="padding:9px 0">Avatar</td>
                    <td><img src="<?= h($avatarSrc) ?>" alt="Avatar" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:1px solid #ddd"></td>
                </tr>
                <?php endif; ?>
                <tr><td class="text-muted" style="padding:9px 0;width:140px">Họ và tên</td><td><strong><?= h($user['full_name']) ?></strong></td></tr>
                <tr><td class="text-muted" style="padding:9px 0">Tên đăng nhập</td><td><?= h($user['username']) ?></td></tr>
                <tr><td class="text-muted" style="padding:9px 0">Email</td><td><?= h($user['email']) ?></td></tr>
                <tr><td class="text-muted" style="padding:9px 0">Số điện thoại</td><td><?= $user['phone'] ? h($user['phone']) : '—' ?></td></tr>
                <tr><td class="text-muted" style="padding:9px 0">Vai trò</td><td>
                    <span class="badge <?= $user['role']==='teacher' ? 'badge-blue' : 'badge-green' ?>">
                        <?= $user['role']==='teacher' ? 'Giáo viên' : 'Sinh viên' ?>
                    </span>
                </td></tr>
            </table>
            <?php if ($user['id'] != $me['id']): ?>
            <div class="mt-4">
                <a href="<?= BASE_URL ?>/messages.php?to=<?= $user['id'] ?>" class="btn btn-primary btn-sm">Nhắn tin</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php renderFooter(); exit;
}

if ($action === 'edit' && $id) {
    requireTeacher();
    $user = db_getUserById($id);
    if (!$user) redirect(BASE_URL . '/users.php');
    if ($user['role'] === 'teacher' && (int)$me['id'] !== (int)$user['id']) {
        flash('error', 'Không có quyền chỉnh sửa tài khoản giáo viên khác.');
        redirect(BASE_URL . '/users.php');
    }
    renderHeader('Sửa người dùng'); ?>
    <div class="page-header">
        <div class="page-header-text">
            <h2>Sửa thông tin</h2>
            <p><?= h($user['full_name']) ?></p>
        </div>
        <a href="<?= BASE_URL ?>/users.php?action=view&id=<?= $id ?>" class="btn btn-outline">Quay lại</a>
    </div>
    <div class="card" style="max-width:500px">
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>/users.php?action=edit&id=<?= $id ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="form-group">
                    <label>Tên đăng nhập</label>
                    <?php if ($user['role'] === 'student'): ?>
                    <input type="text" name="username" value="<?= h($user['username']) ?>" required>
                    <?php else: ?>
                    <input type="text" value="<?= h($user['username']) ?>" disabled style="background:#f1f5f9;cursor:not-allowed">
                    <input type="hidden" name="username" value="<?= h($user['username']) ?>">
                    <div class="text-muted text-sm">Không thể thay đổi</div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Họ và tên *</label>
                    <input type="text" name="full_name" value="<?= h($user['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= h($user['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Số điện thoại</label>
                    <input type="tel" name="phone" value="<?= h($user['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Mật khẩu mới <span class="text-muted">(để trống nếu không đổi)</span></label>
                    <input type="password" name="new_password">
                </div>
                <div class="form-group">
                    <label>Avatar hiện tại</label>
                    <?php if (!empty($user['avatar'])): ?>
                    <?php $avatarSrc = preg_match('/^https?:\/\//i', $user['avatar']) ? $user['avatar'] : BASE_URL . '/file.php?path=' . urlencode($user['avatar']); ?>
                    <div style="margin-bottom:8px"><img src="<?= h($avatarSrc) ?>" alt="Avatar" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:1px solid #ddd"></div>
                    <?php else: ?>
                    <div class="text-muted text-sm" style="margin-bottom:8px">Chưa có avatar</div>
                    <?php endif; ?>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                        <input type="checkbox" id="remove_avatar" name="remove_avatar" value="1">
                        <label for="remove_avatar" style="margin:0;font-weight:400">Xóa avatar hiện tại</label>
                    </div>
                    <input type="file" name="avatar_file" accept="image/png,image/jpeg,image/gif">
                    <div class="text-muted text-sm" style="margin:6px 0">hoặc</div>
                    <input type="url" name="avatar_url" placeholder="https://example.com/avatar.jpg">
                </div>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </form>
        </div>
    </div>
    <?php renderFooter(); exit;
}

$users = db_getAllUsers();
renderHeader('Người dùng'); ?>
<div class="page-header">
    <div class="page-header-text">
        <h2>Danh sách người dùng</h2>
        <p>Tất cả giáo viên và sinh viên</p>
    </div>
    <?php if (isTeacher()): ?>
    <button class="btn btn-primary" onclick="document.getElementById('addForm').classList.toggle('hidden')">Thêm người dùng</button>
    <?php endif; ?>
</div>

<?php if (isTeacher()): ?>
<div id="addForm" class="card mb-4 hidden">
    <div class="card-header"><h3>Thêm người dùng mới</h3></div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/users.php?action=add" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="grid-2">
                <div class="form-group">
                    <label>Tên đăng nhập *</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Mật khẩu *</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Họ và tên *</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Số điện thoại</label>
                    <input type="tel" name="phone">
                </div>
                <div class="form-group">
                    <label>Vai trò</label>
                    <input type="hidden" name="role" value="student">
                    <span class="badge badge-green">Sinh viên</span>
                </div>
                <div class="form-group">
                    <label>Avatar (file)</label>
                    <input type="file" name="avatar_file" accept="image/png,image/jpeg,image/gif">
                </div>
                <div class="form-group">
                    <label>Avatar (URL)</label>
                    <input type="url" name="avatar_url" placeholder="https://example.com/avatar.jpg">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Lưu</button>
            <button type="button" class="btn btn-outline"
                onclick="document.getElementById('addForm').classList.add('hidden')">Hủy</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Họ và tên</th><th>Tên đăng nhập</th><th>Email</th><th>Điện thoại</th><th>Vai trò</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><strong><?= h($u['full_name']) ?></strong></td>
                <td class="text-muted"><?= h($u['username']) ?></td>
                <td><?= h($u['email']) ?></td>
                <td><?= $u['phone'] ? h($u['phone']) : '—' ?></td>
                <td><span class="badge <?= $u['role']==='teacher' ? 'badge-blue' : 'badge-green' ?>">
                    <?= $u['role']==='teacher' ? 'Giáo viên' : 'Sinh viên' ?>
                </span></td>
                <td>
                    <a href="?action=view&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Xem</a>
                    <?php if ($u['id'] != $me['id']): ?>
                    <a href="<?= BASE_URL ?>/messages.php?to=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Nhắn tin</a>
                    <?php endif; ?>
                    <?php if (isTeacher() && $u['role'] !== 'teacher'): ?>
                    <a href="?action=edit&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Sửa</a>
                    <form method="POST" action="?action=delete&id=<?= $u['id'] ?>" style="display:inline"
                          onsubmit="return confirm('Xóa <?= h($u['full_name']) ?>?')">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <button class="btn btn-danger btn-sm">Xóa</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php renderFooter(); ?>
