<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();

$pdo = database();
$accounts = $pdo->query(
    'SELECT u.id, u.username, u.created_at, COUNT(a.id) application_count
     FROM users u LEFT JOIN applications a ON a.user_id = u.id
     GROUP BY u.id, u.username, u.created_at ORDER BY u.created_at ASC'
)->fetchAll();
$flash = pull_flash();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Akun — JejakKarier</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <script>
    if (localStorage.getItem('jejak-karier-theme') === 'dark' ||
        (!localStorage.getItem('jejak-karier-theme') && matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.dataset.theme = 'dark';
    }
    </script>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="dashboard.php">
        <span class="brand-mark"><svg viewBox="0 0 24 24"><path d="M5 7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v10a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 17.5v-10Z"/><path d="M9 5V3.8A1.8 1.8 0 0 1 10.8 2h2.4A1.8 1.8 0 0 1 15 3.8V5M5 10h14M10 13h4"/></svg></span>
        <span>Jejak<span>Karier</span></span>
    </a>
    <div class="header-actions">
        <button class="theme-toggle" type="button" data-theme-toggle aria-label="Ganti tema">
            <svg class="moon-icon" viewBox="0 0 24 24"><path d="M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z"/></svg>
            <svg class="sun-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2"/></svg>
        </button>
        <a class="btn btn-secondary" href="dashboard.php">Kembali ke Dashboard</a>
    </div>
</header>

<main class="container account-container">
    <section class="account-heading">
        <span class="eyebrow"><span></span> PENGATURAN</span>
        <h1>Kelola akun</h1>
        <p>Perbarui informasi masuk dan buat akun terpisah untuk pengguna lain.</p>
    </section>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" data-alert>
            <span><?= $flash['type'] === 'success' ? '✓' : '!' ?></span>
            <p><?= e($flash['message']) ?></p>
            <button type="button" data-close-alert>×</button>
        </div>
    <?php endif; ?>

    <section class="account-grid">
        <article class="settings-card">
            <div class="settings-head"><div class="settings-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg></div><div><h2>Akun Saya</h2><p>Ganti username atau password akun aktif.</p></div></div>
            <form method="post" action="account_action.php" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_profile">
                <label class="field"><span>Username</span><input type="text" name="username" value="<?= e((string) $_SESSION['username']) ?>" minlength="3" maxlength="50" required></label>
                <label class="field"><span>Password Saat Ini <b>*</b></span><input type="password" name="current_password" autocomplete="current-password" required></label>
                <div class="two-fields">
                    <label class="field"><span>Password Baru</span><input type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti"></label>
                    <label class="field"><span>Konfirmasi Password</span><input type="password" name="confirm_password" minlength="8" autocomplete="new-password"></label>
                </div>
                <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
            </form>
        </article>

        <article class="settings-card">
            <div class="settings-head"><div class="settings-icon coral"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M19 8v6M16 11h6"/></svg></div><div><h2>Tambah Akun</h2><p>Data lamaran setiap akun akan terpisah.</p></div></div>
            <form method="post" action="account_action.php" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_account">
                <label class="field"><span>Username Baru</span><input type="text" name="new_username" minlength="3" maxlength="50" placeholder="Contoh: pengguna_baru" required></label>
                <div class="two-fields">
                    <label class="field"><span>Password</span><input type="password" name="account_password" minlength="8" required></label>
                    <label class="field"><span>Konfirmasi</span><input type="password" name="account_password_confirmation" minlength="8" required></label>
                </div>
                <button class="btn btn-primary" type="submit">Buat Akun Baru</button>
            </form>
        </article>
    </section>

    <section class="settings-card account-list-card">
        <div class="settings-head"><div class="settings-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><div><h2>Daftar Akun</h2><p><?= count($accounts) ?> akun terdaftar</p></div></div>
        <div class="account-list">
            <?php foreach ($accounts as $account): ?>
                <div class="account-row">
                    <span class="user-avatar"><?= e(mb_strtoupper(mb_substr($account['username'], 0, 1))) ?></span>
                    <div><strong><?= e($account['username']) ?></strong><small>Bergabung <?= e(format_date_id($account['created_at'])) ?></small></div>
                    <span><?= (int) $account['application_count'] ?> lamaran</span>
                    <?php if ((int) $account['id'] === (int) $_SESSION['user_id']): ?><i>Akun aktif</i><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<script src="assets/app.js"></script>
</body>
</html>
