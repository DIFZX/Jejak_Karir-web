<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_home();
}

verify_csrf();
$action = (string) ($_POST['action'] ?? '');
$pdo = database();
$userId = (int) $_SESSION['user_id'];

if ($action === 'delete') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        flash('error', 'Data yang akan dihapus tidak valid.');
        redirect_home();
    }

    $ownerStatement = $pdo->prepare('SELECT id FROM applications WHERE id = ? AND user_id = ?');
    $ownerStatement->execute([$id, $userId]);
    if (!$ownerStatement->fetchColumn()) {
        flash('error', 'Data tidak ditemukan atau bukan milik akunmu.');
        redirect_home();
    }

    $pdo->beginTransaction();
    $historyStatement = $pdo->prepare('DELETE FROM application_status_history WHERE application_id = ?');
    $historyStatement->execute([$id]);
    $statement = $pdo->prepare('DELETE FROM applications WHERE id = ? AND user_id = ?');
    $statement->execute([$id, $userId]);
    $pdo->commit();
    flash('success', 'Riwayat lamaran berhasil dihapus.');
    redirect_home();
}

if (!in_array($action, ['create', 'update'], true)) {
    flash('error', 'Aksi tidak dikenali.');
    redirect_home();
}

$company = trim((string) ($_POST['company'] ?? ''));
$position = trim((string) ($_POST['position'] ?? ''));
$channel = trim((string) ($_POST['channel'] ?? ''));
$status = trim((string) ($_POST['status'] ?? 'Terkirim'));
$priority = trim((string) ($_POST['priority'] ?? 'Sedang'));
$notes = trim((string) ($_POST['notes'] ?? ''));
$followUpInput = trim((string) ($_POST['follow_up_at'] ?? ''));
$interviewInput = trim((string) ($_POST['interview_at'] ?? ''));
$deadlineInput = trim((string) ($_POST['deadline_at'] ?? ''));
$followUpAt = nullable_datetime($followUpInput);
$interviewAt = nullable_datetime($interviewInput);
$deadlineAt = nullable_datetime($deadlineInput);

$_SESSION['old_input'] = compact(
    'company', 'position', 'channel', 'status', 'priority', 'notes',
    'followUpInput', 'interviewInput', 'deadlineInput'
);

$errors = [];
if ($company === '' || mb_strlen($company) > 150) {
    $errors[] = 'Nama perusahaan wajib diisi (maksimal 150 karakter).';
}
if ($position === '' || mb_strlen($position) > 150) {
    $errors[] = 'Posisi yang dilamar wajib diisi (maksimal 150 karakter).';
}
if ($channel === '' || mb_strlen($channel) > 100) {
    $errors[] = 'Media lamaran wajib diisi (maksimal 100 karakter).';
}
if (!in_array($status, APPLICATION_STATUSES, true)) {
    $errors[] = 'Status lamaran tidak valid.';
}
if (!in_array($priority, APPLICATION_PRIORITIES, true)) {
    $errors[] = 'Prioritas lamaran tidak valid.';
}
if (($followUpInput !== '' && !$followUpAt) || ($interviewInput !== '' && !$interviewAt) || ($deadlineInput !== '' && !$deadlineAt)) {
    $errors[] = 'Format tanggal pengingat atau jadwal tidak valid.';
}
if (mb_strlen($notes) > 2000) {
    $errors[] = 'Catatan maksimal 2.000 karakter.';
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect_home();
}

if ($action === 'create') {
    $pdo->beginTransaction();
    $insertSql =
        'INSERT INTO applications
         (user_id, company, position, channel, status, priority, notes, follow_up_at, interview_at, deadline_at, applied_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
    if (is_postgres_database()) {
        $insertSql .= ' RETURNING id';
    }
    $statement = $pdo->prepare($insertSql);
    $statement->execute([
        $userId, $company, $position, $channel, $status, $priority, $notes ?: null,
        $followUpAt, $interviewAt, $deadlineAt,
    ]);
    $applicationId = is_postgres_database()
        ? (int) $statement->fetchColumn()
        : (int) $pdo->lastInsertId();
    $history = $pdo->prepare(
        'INSERT INTO application_status_history (application_id, status, changed_at) VALUES (?, ?, NOW())'
    );
    $history->execute([$applicationId, $status]);
    $pdo->commit();
    flash('success', 'Lamaran ke ' . $company . ' berhasil dicatat.');
} else {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        flash('error', 'Data yang akan diperbarui tidak valid.');
        redirect_home();
    }

    $currentStatement = $pdo->prepare('SELECT status FROM applications WHERE id = ? AND user_id = ?');
    $currentStatement->execute([$id, $userId]);
    $current = $currentStatement->fetch();
    if (!$current) {
        flash('error', 'Data tidak ditemukan atau bukan milik akunmu.');
        redirect_home();
    }

    $pdo->beginTransaction();
    $statement = $pdo->prepare(
        'UPDATE applications
         SET company = ?, position = ?, channel = ?, status = ?, priority = ?, notes = ?,
             follow_up_at = ?, interview_at = ?, deadline_at = ?
         WHERE id = ? AND user_id = ?'
    );
    $statement->execute([
        $company, $position, $channel, $status, $priority, $notes ?: null,
        $followUpAt, $interviewAt, $deadlineAt, $id, $userId,
    ]);
    if ($current['status'] !== $status) {
        $history = $pdo->prepare(
            'INSERT INTO application_status_history (application_id, status, changed_at) VALUES (?, ?, NOW())'
        );
        $history->execute([$id, $status]);
    }
    $pdo->commit();
    flash('success', 'Riwayat lamaran berhasil diperbarui.');
}

unset($_SESSION['old_input']);
redirect_home();
