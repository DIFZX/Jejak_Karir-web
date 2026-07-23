<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_auth();

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$priority = trim((string) ($_GET['priority'] ?? ''));
$dateFilter = trim((string) ($_GET['applied_date'] ?? ''));
$postgres = is_postgres_database();
$conditions = ['user_id = :user_id'];
$parameters = ['user_id' => (int) $_SESSION['user_id']];

if ($search !== '') {
    $likeOperator = $postgres ? 'ILIKE' : 'LIKE';
    $conditions[] = "(company $likeOperator :search_company OR position $likeOperator :search_position OR channel $likeOperator :search_channel OR notes $likeOperator :search_notes)";
    $searchValue = '%' . $search . '%';
    $parameters['search_company'] = $searchValue;
    $parameters['search_position'] = $searchValue;
    $parameters['search_channel'] = $searchValue;
    $parameters['search_notes'] = $searchValue;
}
if (in_array($status, APPLICATION_STATUSES, true)) {
    $conditions[] = 'status = :status';
    $parameters['status'] = $status;
}
if (in_array($priority, APPLICATION_PRIORITIES, true)) {
    $conditions[] = 'priority = :priority';
    $parameters['priority'] = $priority;
}
if (is_valid_iso_date($dateFilter)) {
    $conditions[] = 'DATE(applied_at) = :applied_date';
    $parameters['applied_date'] = $dateFilter;
}

$statement = database()->prepare(
    'SELECT company, position, channel, status, priority, applied_at,
            follow_up_at, interview_at, deadline_at, notes
     FROM applications WHERE ' . implode(' AND ', $conditions) .
    ' ORDER BY applied_at DESC, id DESC'
);
$statement->execute($parameters);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="riwayat-lamaran-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, [
    'Perusahaan', 'Posisi', 'Melalui', 'Tahapan', 'Prioritas', 'Tanggal Melamar',
    'Follow-up', 'Interview', 'Deadline', 'Catatan',
], ';');

function safe_csv_value(?string $value): string
{
    $value = $value ?? '';
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }
    return $value;
}

while ($row = $statement->fetch()) {
    fputcsv($output, array_map('safe_csv_value', array_values($row)), ';');
}
fclose($output);
exit;
