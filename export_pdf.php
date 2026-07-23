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
$conditions = ['user_id = :user_id'];
$parameters = ['user_id' => (int) $_SESSION['user_id']];

if ($search !== '') {
    $conditions[] = '(company LIKE :search_company OR position LIKE :search_position OR channel LIKE :search_channel OR notes LIKE :search_notes)';
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

$where = ' WHERE ' . implode(' AND ', $conditions);
$statement = database()->prepare('SELECT * FROM applications' . $where . ' ORDER BY applied_at DESC, id DESC');
$statement->execute($parameters);
$rows = $statement->fetchAll();

function pdf_text(string $text): string
{
    $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
    $converted = $converted === false ? $text : $converted;
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $converted);
}

function clip_text(string $text, int $length): string
{
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 3) . '...' : $text;
}

$pages = array_chunk($rows, 22);
if (!$pages) {
    $pages = [[]];
}

$objects = [];
$pageIds = [];
$contentIds = [];
$nextId = 4;
foreach ($pages as $_) {
    $pageIds[] = $nextId++;
    $contentIds[] = $nextId++;
}

$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
$kidReferences = [];
foreach ($pageIds as $pageId) {
    $kidReferences[] = $pageId . ' 0 R';
}
$kids = implode(' ', $kidReferences);
$objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pages) . ' >>';
$objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

foreach ($pages as $pageIndex => $pageRows) {
    $stream = "0.09 0.14 0.23 rg\nBT /F1 20 Tf 42 555 Td (JejakKarier) Tj ET\n";
    $stream .= "0.45 0.50 0.58 rg\nBT /F1 9 Tf 42 537 Td (Laporan Riwayat Lamaran Kerja) Tj ET\n";
    $stream .= "0.95 0.96 0.98 rg\n42 503 758 24 re f\n";
    $stream .= "0.24 0.29 0.38 rg\nBT /F1 7 Tf 49 513 Td (NO.) Tj 28 0 Td (PERUSAHAAN) Tj 165 0 Td (POSISI) Tj 175 0 Td (MELALUI) Tj 110 0 Td (TANGGAL) Tj 125 0 Td (STATUS) Tj ET\n";

    $y = 486;
    foreach ($pageRows as $index => $row) {
        $number = ($pageIndex * 22) + $index + 1;
        if ($index % 2 === 1) {
            $stream .= "0.985 0.987 0.992 rg\n42 " . ($y - 7) . " 758 20 re f\n";
        }
        $date = (new DateTime($row['applied_at']))->format('d/m/Y H:i');
        $values = [
            (string) $number,
            clip_text($row['company'], 27),
            clip_text($row['position'], 29),
            clip_text($row['channel'], 18),
            $date,
            $row['status'] . ' / ' . $row['priority'],
        ];
        $offsets = [49, 77, 242, 417, 527, 652];
        $stream .= "0.20 0.25 0.34 rg\n";
        foreach ($values as $valueIndex => $value) {
            $stream .= "BT /F1 7 Tf {$offsets[$valueIndex]} {$y} Td (" . pdf_text($value) . ") Tj ET\n";
        }
        $stream .= "0.91 0.92 0.94 RG 42 " . ($y - 8) . " m 800 " . ($y - 8) . " l S\n";
        $y -= 20;
    }

    $filterInfo = 'Total: ' . count($rows) . ' lamaran';
    if ($search !== '') {
        $filterInfo .= ' | Pencarian: ' . $search;
    }
    if ($status !== '') {
        $filterInfo .= ' | Status: ' . $status;
    }
    if ($priority !== '') {
        $filterInfo .= ' | Prioritas: ' . $priority;
    }
    if (is_valid_iso_date($dateFilter)) {
        $filterInfo .= ' | Tanggal: ' . format_date_only_id($dateFilter);
    }
    $stream .= "0.45 0.50 0.58 rg\nBT /F1 7 Tf 42 28 Td (" . pdf_text($filterInfo) . ") Tj ET\n";
    $stream .= "BT /F1 7 Tf 698 28 Td (Halaman " . ($pageIndex + 1) . " / " . count($pages) . ") Tj ET\n";

    $objects[$pageIds[$pageIndex]] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentIds[$pageIndex] . ' 0 R >>';
    $objects[$contentIds[$pageIndex]] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
}

ksort($objects);
$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
$offsets = [0];
foreach ($objects as $id => $object) {
    $offsets[$id] = strlen($pdf);
    $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
}
$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($id = 1; $id <= count($objects); $id++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="riwayat-lamaran-' . date('Y-m-d') . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
