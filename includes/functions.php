<?php
declare(strict_types=1);

const APPLICATION_STATUSES = [
    'Terkirim', 'Diproses', 'HR Screening', 'Tes',
    'Interview', 'Offering', 'Ditolak', 'Diterima',
];
const APPLICATION_PRIORITIES = ['Tinggi', 'Sedang', 'Rendah'];
const APPLICATION_CHANNELS = [
    'LinkedIn',
    'JobStreet',
    'Glints',
    'Kalibrr',
    'Loker.id',
    'Loker Nusantara',
    'Website Perusahaan',
    'Email',
    'Referensi',
];

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function redirect_home(): void
{
    header('Location: dashboard.php');
    exit;
}

function format_date_id(string $date): string
{
    $months = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];
    $value = new DateTime($date);

    return $value->format('d') . ' ' . $months[(int) $value->format('n')] . ' ' .
        $value->format('Y') . ', ' . $value->format('H:i');
}

function format_date_only_id(string $date): string
{
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];
    $value = new DateTime($date);
    return $value->format('d') . ' ' . $months[(int) $value->format('n')] . ' ' . $value->format('Y');
}

function relative_date_label(string $date): string
{
    $value = new DateTime($date);
    $today = new DateTime('today');
    $yesterday = (new DateTime('today'))->modify('-1 day');

    if ($value->format('Y-m-d') === $today->format('Y-m-d')) {
        return 'Hari ini';
    }
    if ($value->format('Y-m-d') === $yesterday->format('Y-m-d')) {
        return 'Kemarin';
    }
    return format_date_only_id($date);
}

function status_class(string $status): string
{
    switch ($status) {
        case 'Diproses':
        case 'HR Screening':
            return 'blue';
        case 'Tes':
        case 'Interview':
            return 'purple';
        case 'Offering':
            return 'orange';
        case 'Ditolak':
            return 'red';
        case 'Diterima':
            return 'green';
        default:
            return 'gray';
    }
}

function priority_class(string $priority): string
{
    switch ($priority) {
        case 'Tinggi':
            return 'high';
        case 'Rendah':
            return 'low';
        default:
            return 'medium';
    }
}

function datetime_local_value(?string $date): string
{
    if (!$date) {
        return '';
    }
    return (new DateTime($date))->format('Y-m-d\TH:i');
}

function nullable_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    return $date ? $date->format('Y-m-d H:i:s') : null;
}

function is_valid_iso_date(string $date): bool
{
    $value = DateTime::createFromFormat('!Y-m-d', $date);
    return $value !== false && $value->format('Y-m-d') === $date;
}

function old_input(string $key): string
{
    return e((string) ($_SESSION['old_input'][$key] ?? ''));
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['username']);
}

function require_auth(): void
{
    if (!is_logged_in()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        header('Location: login.php');
        exit;
    }
}
