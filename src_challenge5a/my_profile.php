<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/database.php';
requireLogin();
$me = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'avatar') {
        $avatar = null;
        $source = $_POST['avatar_source'] ?? 'file';

        if ($source === 'file') {
            $file = uploadFile('avatar_file', 'avatars', ALLOWED_IMG_TYPES);
            if ($file) {
                $avatar = $file;
            } else {
                flash('error', 'File không hợp lệ. Hỗ trợ: PNG, JPG, GIF (tối đa 10MB).');
                redirect(BASE_URL . '/my_profile.php');
            }
        } else {
            $url = trim($_POST['avatar_url'] ?? '');
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
                flash('error', 'URL không hợp lệ. Chỉ hỗ trợ http/https.');
                redirect(BASE_URL . '/my_profile.php');
            }
            $avatar = $url;
        }

        db_updateAvatar($me['id'], $avatar);
        $_SESSION['user']['avatar'] = $avatar;
        flash('success', 'Cập nhật avatar thành công!');
        redirect(BASE_URL . '/my_profile.php');
    }

    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (empty($email)) {
        flash('error', 'Email không được để trống.');
        redirect(BASE_URL . '/my_profile.php');
    }
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        flash('error', 'Mật khẩu mới phải có ít nhất 8 ký tự.');
        redirect(BASE_URL . '/my_profile.php');
    }
    if ($newPassword !== '' && $newPassword !== $confirmPassword) {
        flash('error', 'Xác nhận mật khẩu không khớp.');
        redirect(BASE_URL . '/my_profile.php');
    }
    try {
        if ($newPassword !== '') {
            db_updateProfileWithPassword($me['id'], $email, $phone, password_hash($newPassword, PASSWORD_DEFAULT));
        } else {
            db_updateProfile($me['id'], $email, $phone);
        }
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['phone'] = $phone;
        flash('success', 'Cập nhật hồ sơ thành công!');
    } catch (PDOException $e) {
        flash('error', 'Email đã được dùng bởi tài khoản khác.');
    }
    redirect(BASE_URL . '/my_profile.php');
}

$me = db_getUserById($me['id']) ?: currentUser();

function avatarSrc(?string $avatar): string {
    if (!$avatar) return '';
    if (preg_match('/^https?:\/\//i', $avatar)) return $avatar;
    return BASE_URL . '/file.php?path=' . urlencode($avatar);
}

renderHeader('Hồ sơ của tôi'); ?>
<div class="page-header">
    <div class="page-header-text">
        <h2>Hồ sơ của tôi</h2>
        <p>Cập nhật avatar và thông tin liên lạc</p>
    </div>
</div>

<div class="grid-2" style="max-width:900px;align-items:start">

<div class="card">
    <div class="card-header">Avatar</div>
    <div class="card-body">
        <div style="text-align:center;margin-bottom:16px">
            <?php if (!empty($me['avatar'])): ?>
                <img src="<?= h(avatarSrc($me['avatar'])) ?>" alt="Avatar"
                     style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid #ddd">
            <?php else: ?>
                <div style="width:100px;height:100px;border-radius:50%;background:#e5f1fb;
                            display:flex;align-items:center;justify-content:center;
                            font-size:36px;font-weight:700;color:#005fb8;margin:0 auto;border:3px solid #ddd">
                    <?= mb_strtoupper(mb_substr($me['full_name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="avatar">
            <div class="form-group">
                <label>Nguồn ảnh</label>
                <div style="display:flex;gap:20px;margin-bottom:10px">
                    <label style="font-weight:normal;display:flex;align-items:center;gap:5px;cursor:pointer">
                        <input type="radio" name="avatar_source" value="file" checked onchange="toggleAvSrc('file')">
                        Upload file
                    </label>
                    <label style="font-weight:normal;display:flex;align-items:center;gap:5px;cursor:pointer">
                        <input type="radio" name="avatar_source" value="url" onchange="toggleAvSrc('url')">
                        Từ URL
                    </label>
                </div>
                <div id="av-file">
                    <input type="file" name="avatar_file" accept="image/png,image/jpeg,image/gif">
                    <div class="text-muted text-sm" style="margin-top:4px">PNG, JPG, GIF — tối đa 10 MB</div>
                </div>
                <div id="av-url" style="display:none">
                    <input type="url" name="avatar_url" placeholder="https://example.com/photo.jpg">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Đổi avatar</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Thông tin cá nhân</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" value="<?= h($me['username']) ?>" disabled style="background:#f1f5f9;cursor:not-allowed">
                <div class="text-muted text-sm">Không thể thay đổi</div>
            </div>
            <div class="form-group">
                <label>Họ và tên</label>
                <input type="text" value="<?= h($me['full_name']) ?>" disabled style="background:#f1f5f9;cursor:not-allowed">
                <div class="text-muted text-sm">Không thể thay đổi</div>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= h($me['email']) ?>" required>
            </div>
            <div class="form-group">
                <label>Số điện thoại</label>
                <input type="tel" name="phone" value="<?= h($me['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Mật khẩu mới</label>
                <input type="password" name="new_password" autocomplete="new-password" placeholder="Để trống nếu không đổi">
            </div>
            <div class="form-group">
                <label>Xác nhận mật khẩu mới</label>
                <input type="password" name="confirm_password" autocomplete="new-password" placeholder="Nhập lại mật khẩu mới">
            </div>
            <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:.83rem;color:#92400e">
                Có thể thay đổi <strong>email</strong>, <strong>số điện thoại</strong> và <strong>mật khẩu</strong>.
            </div>
            <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
        </form>
    </div>
</div>

</div>

<script>
function toggleAvSrc(src) {
    document.getElementById('av-file').style.display = src === 'file' ? '' : 'none';
    document.getElementById('av-url').style.display  = src === 'url'  ? '' : 'none';
}
</script>
<?php renderFooter(); ?>
