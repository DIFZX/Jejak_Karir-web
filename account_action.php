<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
start_application_session();
require_once __DIR__ . '/includes/functions.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: account.php');
    exit;
}

verify_csrf();
$pdo = database();
$action = (string) ($_POST['action'] ?? '');

function account_redirect(): void
{
    header('Location: account.php');
    exit;
}

if ($action === 'update_profile') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
        flash('error', 'Username harus 3–50 karakter dan hanya boleh berisi huruf, angka, titik, atau garis bawah.');
        account_redirect();
    }

    $statement = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $statement->execute([(int) $_SESSION['user_id']]);
    $user = $statement->fetch();
    if (!$user || !password_verify($currentPassword, $user['password'])) {
        flash('error', 'Password saat ini tidak sesuai.');
        account_redirect();
    }
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        flash('error', 'Password baru minimal 8 karakter.');
        account_redirect();
    }
    if ($newPassword !== $confirmPassword) {
        flash('error', 'Konfirmasi password baru tidak sama.');
        account_redirect();
    }

    try {
        if ($newPassword !== '') {
            $update = $pdo->prepare('UPDATE users SET username = ?, password = ? WHERE id = ?');
            $update->execute([$username, password_hash($newPassword, PASSWORD_DEFAULT), (int) $_SESSION['user_id']]);
        } else {
            $update = $pdo->prepare('UPDATE users SET username = ? WHERE id = ?');
            $update->execute([$username, (int) $_SESSION['user_id']]);
        }
        $_SESSION['username'] = $username;
        flash('success', 'Informasi akun berhasil diperbarui.');
    } catch (PDOException $exception) {
        flash('error', 'Username tersebut sudah digunakan akun lain.');
    }
    account_redirect();
}

if ($action === 'create_account') {
    $username = trim((string) ($_POST['new_username'] ?? ''));
    $password = (string) ($_POST['account_password'] ?? '');
    $confirmation = (string) ($_POST['account_password_confirmation'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
        flash('error', 'Username baru harus 3–50 karakter dan hanya menggunakan huruf, angka, titik, atau garis bawah.');
        account_redirect();
    }
    if (strlen($password) < 8) {
        flash('error', 'Password akun baru minimal 8 karakter.');
        account_redirect();
    }
    if ($password !== $confirmation) {
        flash('error', 'Konfirmasi password akun baru tidak sama.');
        account_redirect();
    }

    try {
        $insert = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
        $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        flash('success', 'Akun ' . $username . ' berhasil dibuat dan dapat langsung digunakan.');
    } catch (PDOException $exception) {
        flash('error', 'Username tersebut sudah terdaftar.');
    }
    account_redirect();
}

flash('error', 'Aksi akun tidak dikenali.');
account_redirect();
