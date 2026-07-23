<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/session.php';
start_application_session();
require_once __DIR__ . '/includes/functions.php';
require_auth();

$databaseError = null;
try {
    $pdo = database();
} catch (Throwable $exception) {
    $databaseError = $exception->getMessage();
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$priorityFilter = trim((string) ($_GET['priority'] ?? ''));
$dateFilter = trim((string) ($_GET['applied_date'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$userId = (int) $_SESSION['user_id'];
$postgres = is_postgres_database();
$applications = [];
$total = 0;
$summary = ['total' => 0, 'process' => 0, 'interview' => 0, 'accepted' => 0];
$monthlyChart = [];
$dailyChart = [];
$channelChart = [];
$reminders = [];
$applicationGroups = [];

if (!$databaseError) {
    $summarySql = $postgres
        ? "SELECT COUNT(*) total,
           COUNT(*) FILTER (WHERE status IN ('Diproses','HR Screening','Tes','Offering')) process,
           COUNT(*) FILTER (WHERE status = 'Interview') interview,
           COUNT(*) FILTER (WHERE status = 'Diterima') accepted
           FROM applications WHERE user_id = ?"
        : "SELECT COUNT(*) total,
           SUM(status IN ('Diproses','HR Screening','Tes','Offering')) process,
           SUM(status = 'Interview') interview,
           SUM(status = 'Diterima') accepted
           FROM applications WHERE user_id = ?";
    $summaryStatement = $pdo->prepare($summarySql);
    $summaryStatement->execute([$userId]);
    $summaryRow = $summaryStatement->fetch();
    $summary = [
        'total' => (int) ($summaryRow['total'] ?? 0),
        'process' => (int) ($summaryRow['process'] ?? 0),
        'interview' => (int) ($summaryRow['interview'] ?? 0),
        'accepted' => (int) ($summaryRow['accepted'] ?? 0),
    ];

    $conditions = ['user_id = :user_id'];
    $parameters = ['user_id' => $userId];
    if ($search !== '') {
        $likeOperator = $postgres ? 'ILIKE' : 'LIKE';
        $conditions[] = "(company $likeOperator :search_company OR position $likeOperator :search_position OR channel $likeOperator :search_channel OR notes $likeOperator :search_notes)";
        $searchValue = '%' . $search . '%';
        $parameters['search_company'] = $searchValue;
        $parameters['search_position'] = $searchValue;
        $parameters['search_channel'] = $searchValue;
        $parameters['search_notes'] = $searchValue;
    }
    if (in_array($statusFilter, APPLICATION_STATUSES, true)) {
        $conditions[] = 'status = :status';
        $parameters['status'] = $statusFilter;
    }
    if (in_array($priorityFilter, APPLICATION_PRIORITIES, true)) {
        $conditions[] = 'priority = :priority';
        $parameters['priority'] = $priorityFilter;
    }
    if (is_valid_iso_date($dateFilter)) {
        $conditions[] = 'DATE(applied_at) = :applied_date';
        $parameters['applied_date'] = $dateFilter;
    }
    $where = ' WHERE ' . implode(' AND ', $conditions);

    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM applications' . $where);
    $countStatement->execute($parameters);
    $total = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);

    $statement = $pdo->prepare(
        'SELECT * FROM applications' . $where .
        ' ORDER BY applied_at DESC, id DESC LIMIT :limit OFFSET :offset'
    );
    foreach ($parameters as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
    $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $statement->execute();
    $applications = $statement->fetchAll();

    if ($applications) {
        $applicationIds = array_map(function ($application) {
            return (int) $application['id'];
        }, $applications);
        $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
        $historyStatement = $pdo->prepare(
            "SELECT h.application_id, h.status, h.changed_at
             FROM application_status_history h
             INNER JOIN applications a ON a.id = h.application_id
             WHERE h.application_id IN ($placeholders) AND a.user_id = ?
             ORDER BY h.changed_at ASC, h.id ASC"
        );
        $historyStatement->execute(array_merge($applicationIds, [$userId]));
        $historyByApplication = [];
        foreach ($historyStatement->fetchAll() as $historyRow) {
            $historyByApplication[(int) $historyRow['application_id']][] = [
                'status' => $historyRow['status'],
                'changed_at' => $historyRow['changed_at'],
            ];
        }
        foreach ($applications as &$application) {
            $application['history'] = $historyByApplication[(int) $application['id']] ?? [];
        }
        unset($application);
    }

    foreach ($applications as $application) {
        $dateKey = (new DateTime($application['applied_at']))->format('Y-m-d');
        if (!isset($applicationGroups[$dateKey])) {
            $applicationGroups[$dateKey] = [
                'label' => relative_date_label($application['applied_at']),
                'items' => [],
            ];
        }
        $applicationGroups[$dateKey]['items'][] = $application;
    }

    $monthlySql = $postgres
        ? "SELECT TO_CHAR(applied_at, 'YYYY-MM') month_key, COUNT(*) total
           FROM applications
           WHERE user_id = ? AND applied_at >= CURRENT_DATE - INTERVAL '5 months'
           GROUP BY TO_CHAR(applied_at, 'YYYY-MM') ORDER BY month_key"
        : "SELECT DATE_FORMAT(applied_at, '%Y-%m') month_key, COUNT(*) total
           FROM applications
           WHERE user_id = ? AND applied_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
           GROUP BY month_key ORDER BY month_key";
    $monthlyStatement = $pdo->prepare($monthlySql);
    $monthlyStatement->execute([$userId]);
    $monthlyRows = [];
    foreach ($monthlyStatement->fetchAll() as $row) {
        $monthlyRows[$row['month_key']] = (int) $row['total'];
    }
    $monthNames = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
        $monthDate = new DateTime('first day of this month');
        $monthDate->modify('-' . $monthOffset . ' month');
        $key = $monthDate->format('Y-m');
        $monthlyChart[] = [
            'label' => $monthNames[(int) $monthDate->format('n')],
            'total' => $monthlyRows[$key] ?? 0,
        ];
    }

    $dailySql = $postgres
        ? "SELECT DATE(applied_at) day_key, COUNT(*) total
           FROM applications
           WHERE user_id = ? AND applied_at >= CURRENT_DATE - INTERVAL '6 days'
           GROUP BY DATE(applied_at) ORDER BY day_key"
        : "SELECT DATE(applied_at) day_key, COUNT(*) total
           FROM applications
           WHERE user_id = ? AND applied_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
           GROUP BY day_key ORDER BY day_key";
    $dailyStatement = $pdo->prepare($dailySql);
    $dailyStatement->execute([$userId]);
    $dailyRows = [];
    foreach ($dailyStatement->fetchAll() as $row) {
        $dailyRows[$row['day_key']] = (int) $row['total'];
    }
    $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
        $dayDate = new DateTime('today');
        $dayDate->modify('-' . $dayOffset . ' day');
        $key = $dayDate->format('Y-m-d');
        $dailyChart[] = [
            'label' => $dayOffset === 0 ? 'Hari ini' : $dayNames[(int) $dayDate->format('w')],
            'total' => $dailyRows[$key] ?? 0,
        ];
    }

    $channelStatement = $pdo->prepare(
        'SELECT channel, COUNT(*) total FROM applications
         WHERE user_id = ? GROUP BY channel ORDER BY total DESC, channel ASC LIMIT 5'
    );
    $channelStatement->execute([$userId]);
    $channelChart = $channelStatement->fetchAll();

    $scheduleStatement = $pdo->prepare(
        "SELECT id, company, position, follow_up_at, interview_at, deadline_at
         FROM applications
         WHERE user_id = ? AND status NOT IN ('Ditolak','Diterima')
           AND (
               follow_up_at IS NOT NULL OR interview_at IS NOT NULL OR deadline_at IS NOT NULL
           )"
    );
    $scheduleStatement->execute([$userId]);
    $scheduleTypes = [
        'follow_up_at' => 'Follow-up',
        'interview_at' => 'Interview',
        'deadline_at' => 'Deadline',
    ];
    $now = new DateTime();
    $limitDate = (new DateTime())->modify('+30 days');
    foreach ($scheduleStatement->fetchAll() as $schedule) {
        foreach ($scheduleTypes as $field => $label) {
            if (!$schedule[$field]) {
                continue;
            }
            $eventDate = new DateTime($schedule[$field]);
            if ($eventDate <= $limitDate) {
                $reminders[] = [
                    'id' => (int) $schedule['id'],
                    'company' => $schedule['company'],
                    'position' => $schedule['position'],
                    'type' => $label,
                    'date' => $schedule[$field],
                    'is_overdue' => $eventDate < $now,
                ];
            }
        }
    }
    usort($reminders, function ($first, $second) {
        return strcmp($first['date'], $second['date']);
    });
    $reminders = array_slice($reminders, 0, 6);
}

$flash = pull_flash();
$hasOldInput = !empty($_SESSION['old_input']);
$oldChannel = (string) ($_SESSION['old_input']['channel'] ?? '');
$oldChannelSource = $oldChannel === ''
    ? ''
    : (in_array($oldChannel, APPLICATION_CHANNELS, true) ? $oldChannel : '__other__');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Aplikasi pencatat riwayat lamaran kerja">
    <title>JejakKarier — Tracker Lamaran Kerja</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <script>
    if (localStorage.getItem('jejak-karier-theme') === 'dark' ||
        (!localStorage.getItem('jejak-karier-theme') && matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.dataset.theme = 'dark';
    }
    </script>
    <link rel="stylesheet" href="<?= e(asset_url('assets/style.css')) ?>">
</head>
<body>
<div class="ambient ambient-one"></div>
<div class="ambient ambient-two"></div>

<header class="topbar">
    <div class="brand-area">
        <button class="menu-toggle" type="button" data-open-sidebar aria-label="Buka menu" aria-expanded="false">
            <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <a class="brand" href="dashboard.php" aria-label="JejakKarier">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v10a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 17.5v-10Z"/><path d="M9 5V3.8A1.8 1.8 0 0 1 10.8 2h2.4A1.8 1.8 0 0 1 15 3.8V5M5 10h14M10 13h4"/></svg>
            </span>
            <span>Jejak<span>Karier</span></span>
        </a>
    </div>
    <div class="header-actions">
        <a class="account-button" href="account.php" title="Kelola akun" aria-label="Kelola akun">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>
        </a>
        <div class="notification-menu">
            <button class="notification-button" type="button" data-notification-toggle aria-label="Buka notifikasi jadwal" aria-expanded="false">
                <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                <?php if ($reminders): ?><span><?= count($reminders) > 9 ? '9+' : count($reminders) ?></span><?php endif; ?>
            </button>
            <div class="notification-dropdown" data-notification-dropdown aria-hidden="true">
                <div class="notification-head">
                    <div><strong>Notifikasi</strong><small>Jadwal dan pengingat terdekat</small></div>
                    <?php if ($reminders): ?><span><?= count($reminders) ?> agenda</span><?php endif; ?>
                </div>
                <div class="notification-list">
                    <?php if (!$reminders): ?>
                        <div class="notification-empty">
                            <span><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></span>
                            <strong>Belum ada notifikasi</strong>
                            <small>Jadwal dan pengingatmu akan muncul di sini.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reminders as $reminder): ?>
                            <a class="notification-item <?= $reminder['is_overdue'] ? 'overdue' : '' ?>" href="#jadwal" data-notification-link>
                                <span class="notification-type-icon">
                                    <?php if ($reminder['type'] === 'Interview'): ?>
                                        <svg viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16M5 5h14v15H4V6a1 1 0 0 1 1-1Z"/><path d="m9 15 2 2 4-4"/></svg>
                                    <?php elseif ($reminder['type'] === 'Deadline'): ?>
                                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 0-2 5M20 5v6h-6"/></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="notification-content">
                                    <span><b><?= e($reminder['type']) ?></b><?php if ($reminder['is_overdue']): ?><i>Terlambat</i><?php endif; ?></span>
                                    <strong><?= e($reminder['company']) ?></strong>
                                    <small><?= e(format_date_id($reminder['date'])) ?> WIB</small>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <a class="notification-footer" href="#jadwal" data-notification-link>Lihat semua jadwal <svg viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"/></svg></a>
            </div>
        </div>
        <div class="account-dropdown-menu">
            <button class="user-menu" type="button" data-account-toggle aria-label="Buka menu akun" aria-expanded="false">
                <span class="user-avatar"><?= e(mb_strtoupper(mb_substr((string) $_SESSION['username'], 0, 1))) ?></span>
                <span class="user-menu-copy"><small>Masuk sebagai</small><strong><?= e((string) $_SESSION['username']) ?></strong></span>
                <svg class="user-chevron" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg>
            </button>
            <div class="account-dropdown" data-account-dropdown aria-hidden="true">
                <div class="account-dropdown-head">
                    <span class="user-avatar"><?= e(mb_strtoupper(mb_substr((string) $_SESSION['username'], 0, 1))) ?></span>
                    <div><small>Akun aktif</small><strong><?= e((string) $_SESSION['username']) ?></strong></div>
                </div>
                <a href="logout.php?to=login">
                    <span><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M19 8v6M16 11h6"/></svg></span>
                    <div><strong>Ganti akun</strong><small>Masuk menggunakan akun lain</small></div>
                </a>
                <a class="account-logout" href="logout.php">
                    <span><svg viewBox="0 0 24 24"><path d="M10 5H5v14h5M14 8l4 4-4 4M8 12h10"/></svg></span>
                    <div><strong>Keluar</strong><small>Kembali ke halaman awal</small></div>
                </a>
            </div>
        </div>
        <button class="btn btn-primary" type="button" data-open-modal>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Catat Lamaran
        </button>
    </div>
</header>

<div class="sidebar-overlay" data-close-sidebar></div>
<aside class="sidebar" id="mainSidebar" aria-hidden="true">
    <div class="sidebar-head">
        <div>
            <span class="user-avatar"><?= e(mb_strtoupper(mb_substr((string) $_SESSION['username'], 0, 1))) ?></span>
            <div><small>Selamat datang</small><strong><?= e((string) $_SESSION['username']) ?></strong></div>
        </div>
        <button type="button" data-close-sidebar aria-label="Tutup menu">×</button>
    </div>
    <nav class="sidebar-nav">
        <span>MENU UTAMA</span>
        <a class="active" href="#dashboard" data-sidebar-link>
            <svg viewBox="0 0 24 24"><path d="M4 13h6V4H4zM14 20h6v-9h-6zM4 20h6v-3H4zM14 7h6V4h-6z"/></svg>
            Dashboard
        </a>
        <a href="#riwayat" data-sidebar-link>
            <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/></svg>
            Riwayat Lamaran
            <i><?= number_format($summary['total']) ?></i>
        </a>
        <a href="#jadwal" data-sidebar-link>
            <svg viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>
            Jadwal & Pengingat
            <?php if ($reminders): ?><i><?= count($reminders) ?></i><?php endif; ?>
        </a>
        <a href="#analitik" data-sidebar-link>
            <svg viewBox="0 0 24 24"><path d="M5 20V10M12 20V4M19 20v-7M3 20h18"/></svg>
            Analitik
        </a>
        <button type="button" class="sidebar-link" data-open-export>
            <svg viewBox="0 0 24 24"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14M7 3h10"/></svg>
            Ekspor Data
            <i>PDF / CSV</i>
        </button>
        <span>AKUN</span>
        <a href="account.php">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg>
            Kelola Akun
        </a>
    </nav>
    <div class="sidebar-bottom">
        <button type="button" data-theme-toggle>
            <svg class="moon-icon" viewBox="0 0 24 24"><path d="M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z"/></svg>
            Ganti tampilan
        </button>
        <a href="logout.php"><svg viewBox="0 0 24 24"><path d="M10 5H5v14h5M14 8l4 4-4 4M8 12h10"/></svg>Keluar</a>
    </div>
</aside>

<main class="container">
    <section class="hero" id="dashboard">
        <div>
            <span class="eyebrow"><span></span> JOB APPLICATION TRACKER</span>
            <h1>Setiap lamaran adalah<br><em>langkah menuju peluang.</em></h1>
            <p>Kelola seluruh perjalanan kariermu dalam satu tempat yang rapi, sederhana, dan mudah dipantau.</p>
        </div>
        <div class="hero-visual" aria-hidden="true">
            <div class="orbit orbit-a"></div>
            <div class="orbit orbit-b"></div>
            <div class="briefcase">
                <svg viewBox="0 0 64 64"><path d="M19 17v-4a6 6 0 0 1 6-6h14a6 6 0 0 1 6 6v4"/><rect x="7" y="17" width="50" height="39" rx="7"/><path d="M7 31c14 7 36 7 50 0M27 35h10"/></svg>
            </div>
            <span class="spark s1">✦</span><span class="spark s2">✦</span><span class="spark s3">·</span>
        </div>
    </section>

    <?php if ($databaseError): ?>
        <div class="alert alert-error persistent">
            <strong>MySQL belum terhubung.</strong>
            <span><?= e($databaseError) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" data-alert>
            <span><?= $flash['type'] === 'success' ? '✓' : '!' ?></span>
            <p><?= e($flash['message']) ?></p>
            <button type="button" aria-label="Tutup" data-close-alert>×</button>
        </div>
    <?php endif; ?>

    <section class="stats-grid" id="ringkasan" aria-label="Ringkasan lamaran">
        <article class="stat-card">
            <div class="stat-icon coral"><svg viewBox="0 0 24 24"><path d="M4 7h16v13H4zM8 7V4h8v3M4 11h16M10 14h4"/></svg></div>
            <div><span>Total Lamaran</span><strong><?= number_format($summary['total']) ?></strong></div>
            <small>Semua riwayat</small>
        </article>
        <article class="stat-card">
            <div class="stat-icon blue"><svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 5v5l3 2"/></svg></div>
            <div><span>Sedang Diproses</span><strong><?= number_format($summary['process']) ?></strong></div>
            <small>Menunggu kabar</small>
        </article>
        <article class="stat-card">
            <div class="stat-icon purple"><svg viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/><path d="m9 15 2 2 4-4"/></svg></div>
            <div><span>Interview</span><strong><?= number_format($summary['interview']) ?></strong></div>
            <small>Tahap wawancara</small>
        </article>
        <article class="stat-card">
            <div class="stat-icon green"><svg viewBox="0 0 24 24"><path d="M20 7 10 17l-5-5"/></svg></div>
            <div><span>Diterima</span><strong><?= number_format($summary['accepted']) ?></strong></div>
            <small>Kabar terbaik</small>
        </article>
    </section>

    <section class="reminder-panel" id="jadwal">
        <div class="section-heading">
            <div class="heading-icon"><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></div>
            <div><h2>Pengingat & Jadwal</h2><p>Agenda terdekat dalam 30 hari</p></div>
        </div>
        <?php if ($reminders): ?>
            <div class="reminder-list">
                <?php foreach ($reminders as $reminder): ?>
                    <article class="reminder-item <?= $reminder['is_overdue'] ? 'overdue' : '' ?>">
                        <span class="reminder-type"><?= e($reminder['type']) ?></span>
                        <strong><?= e($reminder['company']) ?></strong>
                        <small><?= e($reminder['position']) ?></small>
                        <time><?= e(format_date_id($reminder['date'])) ?></time>
                        <?php if ($reminder['is_overdue']): ?><i>Terlambat</i><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="schedule-empty"><svg viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg><span>Belum ada jadwal dalam 30 hari ke depan.</span></div>
        <?php endif; ?>
    </section>

    <section class="analytics-grid" id="analitik">
        <article class="chart-card">
            <div class="chart-head">
                <div><h2>Aktivitas Lamaran</h2><p data-chart-subtitle>Tujuh hari terakhir</p></div>
                <div class="chart-actions">
                    <div class="chart-mode">
                        <button class="active" type="button" data-chart-button="daily">Per Hari</button>
                        <button type="button" data-chart-button="monthly">Per Bulan</button>
                    </div>
                    <span>Total <?= number_format($summary['total']) ?></span>
                </div>
            </div>
            <?php $maxDaily = max(1, ...array_column($dailyChart, 'total')); ?>
            <div class="bar-chart" data-chart="daily">
                <?php foreach ($dailyChart as $day): ?>
                    <div class="bar-column">
                        <span><?= (int) $day['total'] ?></span>
                        <div><i style="height: <?= max(4, ((int) $day['total'] / $maxDaily) * 100) ?>%"></i></div>
                        <small><?= e($day['label']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php $maxMonthly = max(1, ...array_column($monthlyChart, 'total')); ?>
            <div class="bar-chart is-hidden" data-chart="monthly">
                <?php foreach ($monthlyChart as $month): ?>
                    <div class="bar-column">
                        <span><?= (int) $month['total'] ?></span>
                        <div><i style="height: <?= max(4, ((int) $month['total'] / $maxMonthly) * 100) ?>%"></i></div>
                        <small><?= e($month['label']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
        <article class="chart-card">
            <div class="chart-head"><div><h2>Kanal Paling Efektif</h2><p>Sumber lamaran terbanyak</p></div></div>
            <div class="channel-chart">
                <?php if (!$channelChart): ?>
                    <div class="mini-empty">Belum ada data kanal.</div>
                <?php else: ?>
                    <?php $maxChannel = max(1, ...array_map('intval', array_column($channelChart, 'total'))); ?>
                    <?php foreach ($channelChart as $channelRow): ?>
                        <div class="channel-row">
                            <div><span><?= e($channelRow['channel']) ?></span><strong><?= (int) $channelRow['total'] ?></strong></div>
                            <div class="progress"><i style="width: <?= ((int) $channelRow['total'] / $maxChannel) * 100 ?>%"></i></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="data-panel transaction-panel" id="riwayat">
        <div class="panel-head">
            <div>
                <h2>Riwayat Lamaran</h2>
                <p><?= number_format($total) ?> data ditemukan</p>
            </div>
            <form class="filters" method="get" action="dashboard.php">
                <label class="search-box">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari perusahaan, posisi..." aria-label="Cari lamaran">
                </label>
                <select name="status" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <?php foreach (APPLICATION_STATUSES as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="priority" aria-label="Filter prioritas">
                    <option value="">Semua prioritas</option>
                    <?php foreach (APPLICATION_PRIORITIES as $priority): ?>
                        <option value="<?= e($priority) ?>" <?= $priorityFilter === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="date-filter" title="Filter tanggal melamar">
                    <svg viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg>
                    <input type="date" name="applied_date" value="<?= e($dateFilter) ?>" aria-label="Tanggal melamar">
                </label>
                <button class="btn btn-filter" type="submit">Cari</button>
                <?php if ($search !== '' || $statusFilter !== '' || $priorityFilter !== '' || $dateFilter !== ''): ?>
                    <a class="clear-filter" href="dashboard.php" title="Hapus filter">×</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="transaction-history">
            <?php if (!$applicationGroups): ?>
                <div class="empty-state">
                    <div><svg viewBox="0 0 24 24"><path d="M5 7h14v13H5zM9 7V4h6v3M8 12h8M8 16h5"/></svg></div>
                    <h3><?= $search || $statusFilter || $priorityFilter || $dateFilter ? 'Data tidak ditemukan' : 'Belum ada riwayat lamaran' ?></h3>
                    <p><?= $search || $statusFilter || $priorityFilter || $dateFilter ? 'Coba gunakan kata kunci, tanggal, atau filter lain.' : 'Mulai catat lamaran pertamamu dan pantau progresnya di sini.' ?></p>
                    <?php if (!$search && !$statusFilter && !$priorityFilter && !$dateFilter): ?>
                        <button class="text-button" type="button" data-open-modal>+ Catat lamaran pertama</button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($applicationGroups as $group): ?>
                    <section class="transaction-group">
                        <div class="transaction-date">
                            <h3><?= e($group['label']) ?></h3>
                            <span><?= count($group['items']) ?> aktivitas</span>
                        </div>
                        <div class="transaction-items">
                            <?php foreach ($group['items'] as $application): ?>
                                <article class="transaction-item">
                                    <span class="transaction-logo"><?= e(mb_strtoupper(mb_substr($application['company'], 0, 1))) ?></span>
                                    <div class="transaction-main">
                                        <div class="transaction-title">
                                            <strong><?= e($application['company']) ?></strong>
                                            <time><?= e((new DateTime($application['applied_at']))->format('H:i')) ?> WIB</time>
                                        </div>
                                        <p><?= e($application['position']) ?> <span>via <?= e($application['channel']) ?></span></p>
                                        <div class="transaction-meta">
                                            <span class="badge <?= status_class($application['status']) ?>"><i></i><?= e($application['status']) ?></span>
                                            <span class="priority-badge <?= priority_class($application['priority']) ?>"><i></i>Prioritas <?= e($application['priority']) ?></span>
                                            <?php if ($application['interview_at']): ?>
                                                <span class="transaction-schedule"><svg viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16M5 5h14v15H4V6a1 1 0 0 1 1-1Z"/></svg>Interview <?= e(format_date_id($application['interview_at'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($application['notes']): ?><small class="transaction-note"><?= e($application['notes']) ?></small><?php endif; ?>
                                    </div>
                                    <div class="row-actions transaction-actions">
                                        <button type="button" class="icon-button edit-button" title="Edit" aria-label="Edit <?= e($application['company']) ?>"
                                                data-application="<?= e(json_encode($application, JSON_UNESCAPED_UNICODE)) ?>">
                                            <svg viewBox="0 0 24 24"><path d="m14 5 5 5M4 20l4-1 11-11a2 2 0 0 0-3-3L5 16l-1 4Z"/></svg>
                                        </button>
                                        <form method="post" action="action.php" data-delete-form data-company="<?= e($application['company']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $application['id'] ?>">
                                            <button type="submit" class="icon-button delete-button" title="Hapus" aria-label="Hapus <?= e($application['company']) ?>">
                                                <svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7M10 11v5M14 11v5"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (($totalPages ?? 1) > 1): ?>
            <nav class="pagination" aria-label="Navigasi halaman">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>" href="?q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&priority=<?= urlencode($priorityFilter) ?>&applied_date=<?= urlencode($dateFilter) ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>

<footer>
    <span>JejakKarier</span>
    <p>Catat langkahmu. Raih karier impianmu.</p>
</footer>

<div class="modal-backdrop <?= $hasOldInput ? 'visible' : '' ?>" id="applicationModal" aria-hidden="<?= $hasOldInput ? 'false' : 'true' ?>">
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-head">
            <div><span class="modal-kicker">RIWAYAT LAMARAN</span><h2 id="modalTitle">Catat Lamaran Baru</h2></div>
            <button type="button" class="modal-close" data-close-modal aria-label="Tutup">
                <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <form method="post" action="action.php" id="applicationForm">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="applicationId" value="">
            <div class="form-grid">
                <label class="field full">
                    <span>Nama Perusahaan <b>*</b></span>
                    <input type="text" name="company" id="company" maxlength="150" value="<?= old_input('company') ?>" placeholder="Contoh: PT Teknologi Nusantara" required autofocus>
                </label>
                <label class="field">
                    <span>Posisi yang Dilamar <b>*</b></span>
                    <input type="text" name="position" id="position" maxlength="150" value="<?= old_input('position') ?>" placeholder="Contoh: UI/UX Designer" required>
                </label>
                <div class="field">
                    <span>Melalui <b>*</b></span>
                    <select name="channel_source" id="channelSource" required aria-describedby="channelHelp">
                        <option value="" disabled <?= $oldChannelSource === '' ? 'selected' : '' ?>>Pilih sumber lamaran</option>
                        <?php foreach (APPLICATION_CHANNELS as $channelOption): ?>
                            <option value="<?= e($channelOption) ?>" <?= $oldChannelSource === $channelOption ? 'selected' : '' ?>><?= e($channelOption) ?></option>
                        <?php endforeach; ?>
                        <option value="__other__" <?= $oldChannelSource === '__other__' ? 'selected' : '' ?>>Lainnya</option>
                    </select>
                    <small class="field-help" id="channelHelp">Pilih Lainnya jika sumber belum tersedia.</small>
                    <label class="channel-custom <?= $oldChannelSource === '__other__' ? 'visible' : '' ?>" id="channelCustomField">
                        <span>Tulis sumber lainnya <b>*</b></span>
                        <input type="text" name="channel_custom" id="channelCustom" maxlength="100"
                               value="<?= $oldChannelSource === '__other__' ? e($oldChannel) : '' ?>"
                               placeholder="Contoh: Instagram" <?= $oldChannelSource === '__other__' ? 'required' : 'disabled' ?>>
                    </label>
                </div>
                <label class="field">
                    <span>Tahapan Rekrutmen</span>
                    <select name="status" id="applicationStatus">
                        <?php foreach (APPLICATION_STATUSES as $status): ?>
                            <option value="<?= e($status) ?>" <?= old_input('status') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Prioritas</span>
                    <select name="priority" id="applicationPriority">
                        <?php foreach (APPLICATION_PRIORITIES as $priority): ?>
                            <option value="<?= e($priority) ?>" <?= old_input('priority') === $priority || (old_input('priority') === '' && $priority === 'Sedang') ? 'selected' : '' ?>><?= e($priority) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="schedule-toggle full">
                    <input type="checkbox" id="scheduleEnabled" <?= old_input('followUpInput') !== '' || old_input('interviewInput') !== '' || old_input('deadlineInput') !== '' ? 'checked' : '' ?>>
                    <span class="custom-check"><svg viewBox="0 0 24 24"><path d="m6 12 4 4 8-9"/></svg></span>
                    <span>
                        <strong>Aktifkan jadwal & pengingat</strong>
                        <small>Tambahkan follow-up, interview, atau deadline</small>
                    </span>
                </label>
                <div class="schedule-fields full" id="scheduleFields">
                    <div class="form-section-title full"><span>Jadwal & Pengingat</span><i></i></div>
                    <label class="field">
                        <span>Pengingat Follow-up</span>
                        <input type="datetime-local" name="follow_up_at" id="followUpAt" value="<?= old_input('followUpInput') ?>">
                    </label>
                    <label class="field">
                        <span>Jadwal Interview</span>
                        <input type="datetime-local" name="interview_at" id="interviewAt" value="<?= old_input('interviewInput') ?>">
                    </label>
                    <label class="field full">
                        <span>Deadline Tes / Dokumen</span>
                        <input type="datetime-local" name="deadline_at" id="deadlineAt" value="<?= old_input('deadlineInput') ?>">
                    </label>
                </div>
                <label class="field full">
                    <span>Catatan <small>(opsional)</small></span>
                    <textarea name="notes" id="notes" maxlength="2000" rows="3" placeholder="Tambahkan informasi penting tentang lamaran ini..."><?= old_input('notes') ?></textarea>
                </label>
                <div class="status-history full" id="statusHistory">
                    <div class="form-section-title"><span>Riwayat Tahapan</span><i></i></div>
                    <div class="timeline" id="statusTimeline"></div>
                </div>
            </div>
            <div class="auto-time">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                Tanggal dan waktu melamar tercatat otomatis saat data disimpan.
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-primary" id="submitButton">Simpan Lamaran</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" id="exportModal" aria-hidden="true">
    <section class="modal export-modal" role="dialog" aria-modal="true" aria-labelledby="exportModalTitle">
        <div class="modal-head">
            <div><span class="modal-kicker">UNDUH LAPORAN</span><h2 id="exportModalTitle">Ekspor Data Lamaran</h2></div>
            <button type="button" class="modal-close" data-close-export aria-label="Tutup">
                <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <form method="get" class="export-form" id="exportForm">
            <p class="export-intro">Pilih data yang ingin dimasukkan ke dalam laporan.</p>
            <div class="export-scope-options">
                <label class="export-option">
                    <input type="radio" name="export_scope" value="all" checked>
                    <span class="export-radio"></span>
                    <span class="export-option-icon"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM8 9h8M8 13h8M8 17h5"/></svg></span>
                    <span><strong>Semua Data</strong><small>Ekspor seluruh riwayat lamaran akunmu</small></span>
                </label>
                <label class="export-option">
                    <input type="radio" name="export_scope" value="date">
                    <span class="export-radio"></span>
                    <span class="export-option-icon"><svg viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"/></svg></span>
                    <span><strong>Tanggal Tertentu</strong><small>Ekspor semua lamaran pada satu tanggal</small></span>
                </label>
            </div>
            <label class="field export-date-field" id="exportDateField">
                <span>Pilih Tanggal</span>
                <input type="date" name="applied_date" id="exportDate" disabled>
            </label>
            <div class="export-actions">
                <button type="button" class="btn btn-cancel" data-close-export>Batal</button>
                <button type="submit" class="btn btn-pdf" formaction="export_pdf.php">
                    <svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7zM14 3v5h5M10 13h4M10 17h4"/></svg>
                    Ekspor PDF
                </button>
                <button type="submit" class="btn btn-csv" formaction="export_csv.php">
                    <svg viewBox="0 0 24 24"><path d="M7 3h7l4 4v14H7zM14 3v5h5M10 13h6M10 17h6"/></svg>
                    Ekspor CSV
                </button>
            </div>
        </form>
    </section>
</div>

<div class="confirm-backdrop" id="confirmModal" aria-hidden="true">
    <section class="confirm-box" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle">
        <div class="confirm-icon"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7"/></svg></div>
        <h2 id="confirmTitle">Hapus riwayat?</h2>
        <p>Lamaran ke <strong id="deleteCompany"></strong> akan dihapus permanen.</p>
        <div><button class="btn btn-cancel" type="button" data-cancel-delete>Batal</button><button class="btn btn-danger" type="button" data-confirm-delete>Ya, Hapus</button></div>
    </section>
</div>

<?php unset($_SESSION['old_input']); ?>
<script src="<?= e(asset_url('assets/app.js')) ?>"></script>
</body>
</html>
