<?php
if (is_admin()) redirect('admin/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $admin = db()->query('SELECT * FROM admins ORDER BY id ASC LIMIT 1')->fetch();
    if ($admin && password_verify($_POST['password'] ?? '', $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        redirect('admin/index.php');
    }
    $error = 'Password admin tidak tepat.';
}
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><link href="https://fonts.googleapis.com/css2?family=Inter&family=Playfair+Display:wght@700&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"></head>
<body class="bg-[#101b35] grid place-items-center min-h-screen"><form method="post" class="bg-white p-8 w-[min(92%,410px)]"><a class="brand" href="<?= url() ?>">TRI<span>WEAR</span></a><h1 class="serif text-4xl mt-7">Admin login.</h1><?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><div class="field"><label>Password admin</label><input autofocus type="password" name="password" required autocomplete="current-password"></div><button class="btn gold w-full mt-3">SIGN IN</button></form></body></html>
