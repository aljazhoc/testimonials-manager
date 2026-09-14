<?php
$error = $loginError ?? '';
$base  = APP_BASE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Testimonials Manager</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
</head>
<body class="login-body">
<div class="login-card">
    <h1 class="login-title">Testimonials Manager</h1>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="<?= $base ?>/?action=login">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" autofocus required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>
</div>
<script src="<?= $base ?>/assets/js/app.js"></script>
</body>
</html>
