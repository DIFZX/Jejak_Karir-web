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
$databaseError = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        try {
            $statement = database()->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
            $statement->execute([$username]);
            $user = $statement->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                unset($_SESSION['csrf_token']);

                $destination = (string) ($_SESSION['intended_url'] ?? 'dashboard.php');
                unset($_SESSION['intended_url']);
                if (strpos($destination, '/') === 0 && strpos($destination, '//') !== 0) {
                    header('Location: ' . $destination);
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            }

            $error = 'Username atau password salah.';
        } catch (Throwable $exception) {
            $databaseError = $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — JejakKarier</title>
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
<body class="login-page">
<main class="login-shell">
    <section class="login-story">
        <a class="brand login-brand" href="index.php">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v10a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 17.5v-10Z"/><path d="M9 5V3.8A1.8 1.8 0 0 1 10.8 2h2.4A1.8 1.8 0 0 1 15 3.8V5M5 10h14M10 13h4"/></svg>
            </span>
            <span>Jejak<span>Karier</span></span>
        </a>
        <div class="story-content">
            <span class="eyebrow"><span></span> JOB APPLICATION TRACKER</span>
            <h1>Karier impian dimulai dari <em>satu langkah.</em></h1>
            <p>Simpan perjalananmu, pantau setiap peluang, dan tetap fokus menuju tujuan kariermu.</p>
            <div class="story-points">
                <span><i>✓</i> Catat setiap lamaran</span>
                <span><i>✓</i> Pantau progres seleksi</span>
                <span><i>✓</i> Ekspor laporan kapan saja</span>
            </div>
        </div>
        <p class="story-footer">Catat langkahmu. Raih karier impianmu.</p>
    </section>

    <section class="login-form-side">
        <div class="login-card">
            <a class="back-home" href="index.php">← Kembali ke beranda</a>
            <div class="login-icon">
                <svg viewBox="0 0 24 24"><path d="M5 10h14v10H5zM8 10V7a4 4 0 0 1 8 0v3M12 14v2"/></svg>
            </div>
            <span class="modal-kicker">SELAMAT DATANG</span>
            <h2>Masuk ke akunmu</h2>
            <p class="login-subtitle">Lanjutkan mengelola perjalanan kariermu.</p>

            <?php if ($error || $databaseError): ?>
                <div class="login-error"><span>!</span><?= e($error ?: $databaseError) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label class="field">
                    <span>Username</span>
                    <div class="input-with-icon">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>
                        <input type="text" name="username" value="<?= e($username) ?>" placeholder="Masukkan username" autocomplete="username" maxlength="50" required autofocus>
                    </div>
                </label>
                <label class="field">
                    <span>Password</span>
                    <div class="input-with-icon">
                        <svg viewBox="0 0 24 24"><path d="M5 10h14v10H5zM8 10V7a4 4 0 0 1 8 0v3"/></svg>
                        <input type="password" name="password" id="loginPassword" placeholder="Masukkan password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" aria-label="Tampilkan password" data-toggle-password>
                            <svg viewBox="0 0 24 24"><path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                        </button>
                    </div>
                </label>
                <button class="btn btn-primary login-submit" type="submit">
                    Masuk ke JejakKarier
                    <svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg>
                </button>
            </form>
            <p class="secure-note">
                <svg viewBox="0 0 24 24"><path d="M7 10V7a5 5 0 0 1 10 0v3M5 10h14v11H5z"/></svg>
                Sesi masuk dilindungi dan password tersimpan secara aman.
            </p>
            <p class="auth-switch">Belum memiliki akun? <a href="register.php">Daftar sekarang</a></p>
        </div>
    </section>
</main>
<script>
document.querySelector('[data-toggle-password]')?.addEventListener('click', function () {
    const input = document.querySelector('#loginPassword');
    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    this.setAttribute('aria-label', visible ? 'Tampilkan password' : 'Sembunyikan password');
});
</script>
</body>
</html>
