<?php
function renderHeader(string $title = 'Quản lý'): void {
    $user = currentUser();
    $nav = [
        ['users.php',       'Người dùng'],
        ['assignments.php', 'Bài tập'],
        ['messages.php',    'Tin nhắn'],
        ['challenges.php',  'Challenge'],
        ['my_profile.php',  'Hồ sơ'],
    ];
    $current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background: #f9f9f9; color: #333; font-size: 14px; line-height: 1.5; 
        }
        .header { 
            background: #fff; border-bottom: 1px solid #ddd; padding: 0 20px; 
            display: flex; justify-content: space-between; align-items: center; height: 50px; 
            position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }
        .nav { display: flex; gap: 5px; }
        .nav a { 
            text-decoration: none; color: #555; padding: 6px 12px; border-radius: 4px; 
            transition: background 0.2s; 
        }
        .nav a:hover { background: #f0f0f0; color: #000; }
        .nav a.active { background: #e5f1fb; color: #005fb8; font-weight: 500; }
        .user-menu { display: flex; align-items: center; gap: 15px; font-size: 13px; }
        .user-menu .logout { color: #d13438; text-decoration: none; font-weight: 500; }
        .user-menu .logout:hover { text-decoration: underline; }
        .main { width: 100%; max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        
        .page-header { margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .page-header h2 { font-size: 20px; font-weight: 600; color: #111; }
        .page-header p { color: #666; font-size: 13px; margin-top: 5px; }
        
        .card { background: #fff; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 20px; }
        .card-header { padding: 12px 15px; border-bottom: 1px solid #ddd; font-weight: 600; background: #fafafa; }
        .card-body { padding: 15px; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 500; margin-bottom: 5px; font-size: 13px; color: #222; }
        input[type=text], input[type=email], input[type=password], input[type=tel],
        textarea, select { 
            width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; 
            font-family: inherit; font-size: 13px; 
        }
        input:focus, textarea:focus, select:focus { outline: none; border-color: #005fb8; }
        textarea { resize: vertical; min-height: 80px; }
        .btn { 
            display: inline-block; padding: 7px 14px; border-radius: 4px; cursor: pointer; 
            text-decoration: none; font-size: 13px; font-weight: 500; text-align: center; border: 1px solid transparent; 
        }
        .btn-primary { background: #005fb8; color: #fff; border-color: #005fb8; }
        .btn-primary:hover { background: #004c93; }
        .btn-danger { background: #d13438; color: #fff; border-color: #d13438; }
        .btn-danger:hover { background: #a82a2d; }
        .btn-outline { background: #fff; color: #333; border-color: #ccc; }
        .btn-outline:hover { background: #f5f5f5; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #fafafa; font-weight: 600; color: #555; }
        tr:hover td { background: #fdfdfd; }
        
        .alert { padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 13px; }
        .alert-success { background: #dff6dd; color: #107c10; border: 1px solid #c5eec2; }
        .alert-error { background: #fde7e9; color: #a80000; border: 1px solid #fbc9ce; }
        
        .badge { padding: 3px 6px; border-radius: 3px; font-size: 11px; font-weight: 500; }
        .badge-blue { background: #e5f1fb; color: #005fb8; }
        .badge-green { background: #dff6dd; color: #107c10; }
        .badge-red { background: #fde7e9; color: #a80000; }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .table-wrap { overflow-x: auto; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 8px; }
        .mt-4 { margin-top: 16px; }
        .mb-4 { margin-bottom: 16px; }
        .hidden { display: none; }
        .text-muted { color: #888; font-size: 13px; }
        .text-sm { font-size: 12px; }
        .page-header-text h2 { font-size: 20px; font-weight: 600; color: #111; }
        .page-header-text p { color: #888; font-size: 13px; margin-top: 5px; }
        .alert-info { background: #e5f1fb; color: #005fb8; border: 1px solid #c5ddf6; }
        pre { background: #fafafa; padding: 14px; border-radius: 4px; white-space: pre-wrap; font-family: inherit; line-height: 1.8; border: 1px solid #ddd; }
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,.4); z-index: 999; justify-content: center; align-items: center;
        }
        .modal-overlay.show { display: flex; }
        @media (max-width: 700px) {
            .header { flex-direction: column; height: auto; padding: 10px; gap: 8px; }
            .nav { flex-wrap: wrap; justify-content: center; }
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="header">
    <nav class="nav">
        <?php foreach ($nav as [$file, $label]): ?>
        <a href="<?= BASE_URL . '/' . $file ?>" class="<?= $current === $file ? 'active' : '' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="user-menu">
        <?php
        $avSrc = '';
        if (!empty($user['avatar'])) {
            $avSrc = preg_match('/^https?:\/\//i', $user['avatar'])
                ? $user['avatar']
                : BASE_URL . '/file.php?path=' . urlencode($user['avatar']);
        }
        if ($avSrc): ?>
            <img src="<?= h($avSrc) ?>" alt=""
                 style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:1px solid #ddd;flex-shrink:0">
        <?php else: ?>
            <div style="width:26px;height:26px;border-radius:50%;background:#e5f1fb;display:flex;align-items:center;
                        justify-content:center;font-size:11px;font-weight:700;color:#005fb8;flex-shrink:0">
                <?= mb_strtoupper(mb_substr($user['full_name'], 0, 1, 'UTF-8'), 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <span><?= h($user['full_name']) ?> (<?= $user['role'] === 'teacher' ? 'GV' : 'SV' ?>)</span>
        <a href="<?= BASE_URL ?>/logout.php" class="logout">Đăng xuất</a>
    </div>
</header>
<main class="main">
<?php
    $flash = getFlash();
    if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?= h($flash['msg']) ?>
    </div>
<?php endif;
}

function renderFooter(): void { ?>
</main>
</body>
</html>
<?php }
