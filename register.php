<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
        $error = 'Username harus 3–50 karakter dan hanya boleh berisi huruf, angka, titik, atau garis bawah.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $confirmation) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        try {
            $pdo = database();
            $insertSql = 'INSERT INTO users (username, password) VALUES (?, ?)';
            if (is_postgres_database()) {
                $insertSql .= ' RETURNING id';
            }
            $statement = $pdo->prepare($insertSql);
            $statement->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $newUserId = is_postgres_database()
                ? (int) $statement->fetchColumn()
                : (int) $pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            unset($_SESSION['csrf_token']);
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $exception) {
            $error = 'Username tersebut sudah digunakan. Silakan pilih username lain.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar — JejakKarier</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="login-story">
        <a class="brand login-brand" href="index.php">
            <span class="brand-mark"><svg viewBox="0 0 24 24"><path d="M5 7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v10a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 17.5v-10Z"/><path d="M9 5V4h6v1M5 10h14"/></svg></span>
            <span>Jejak<span>Karier</span></span>
        </a>
        <div class="story-content">
            <span class="eyebrow"><span></span> MULAI PERJALANANMU</span>
            <h1>Satu akun untuk <em>setiap peluang.</em></h1>
            <p>Buat akun pribadi dan mulai kelola seluruh perjalanan lamaran kerjamu dengan lebih terarah.</p>
            <div class="story-points"><span><i>✓</i> Data setiap akun terpisah</span><span><i>✓</i> Pantau tahapan dan jadwal</span><span><i>✓</i> Ekspor laporan kapan saja</span></div>
        </div>
        <p class="story-footer">JejakKarier — catat langkahmu menuju karier impian.</p>
    </section>
    <section class="login-form-side">
        <div class="login-card">
            <a class="back-home" href="index.php">← Kembali ke beranda</a>
            <div class="login-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M19 8v6M16 11h6"/></svg></div>
            <span class="modal-kicker">AKUN BARU</span>
            <h2>Mulai dengan JejakKarier</h2>
            <p class="login-subtitle">Buat akun untuk menyimpan riwayat lamaranmu.</p>
            <?php if ($error): ?><div class="login-error"><span>!</span><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="register.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label class="field"><span>Username</span><div class="input-with-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg><input type="text" name="username" value="<?= e($username) ?>" minlength="3" maxlength="50" placeholder="Pilih username" autocomplete="username" required autofocus></div></label>
                <label class="field"><span>Password</span><div class="input-with-icon"><svg viewBox="0 0 24 24"><path d="M5 10h14v10H5zM8 10V7a4 4 0 0 1 8 0v3"/></svg><input type="password" name="password" minlength="8" placeholder="Minimal 8 karakter" autocomplete="new-password" required></div></label>
                <label class="field"><span>Konfirmasi Password</span><div class="input-with-icon"><svg viewBox="0 0 24 24"><path d="M5 10h14v10H5zM8 10V7a4 4 0 0 1 8 0v3"/></svg><input type="password" name="password_confirmation" minlength="8" placeholder="Ulangi password" autocomplete="new-password" required></div></label>
                <button class="btn btn-primary login-submit" type="submit">Buat Akun Saya<svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></button>
            </form>
            <p class="auth-switch">Sudah memiliki akun? <a href="login.php">Masuk di sini</a></p>
        </div>
    </section>
</main>
</body>
</html>
