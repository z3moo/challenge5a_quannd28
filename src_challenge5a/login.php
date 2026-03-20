<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
if (!empty($_SESSION['user'])) redirect(BASE_URL . '/users.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = db_getUserByUsername($username);

    if ($user && isset($user['password']) && password_verify($password, $user['password'])) {
        unset($user['password']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        redirect(BASE_URL . '/users.php');
    }
    $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            width: 100%;
            max-width: 360px;
            background: #fff;
            border: 1px solid #ddd;
            padding: 20px;
        }
        h1 {
            margin: 0 0 16px;
            font-size: 22px;
        }
        .form-group {
            margin-bottom: 12px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            font-size: 14px;
        }
        .error {
            margin-bottom: 12px;
            color: #b00020;
            font-size: 14px;
        }
        button {
            width: 100%;
            padding: 10px;
            border: 1px solid #999;
            background: #eee;
            cursor: pointer;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Đăng nhập</h1>
        <?php if ($error): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Đăng nhập</button>
        </form>
    </div>
</body>
</html>
