<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$requestPath = '/' . ltrim(preg_replace('#/+#', '/', $requestPath) ?? '/', '/');

if (PHP_SAPI === 'cli-server') {
    $localFile = $projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $requestPath);
    if (strpos($requestPath, '/assets/') === 0 && is_file($localFile)) {
        return false;
    }
}

$routes = [
    '/' => 'index.php',
    '/index.php' => 'index.php',
    '/login.php' => 'login.php',
    '/register.php' => 'register.php',
    '/dashboard.php' => 'dashboard.php',
    '/action.php' => 'action.php',
    '/account.php' => 'account.php',
    '/account_action.php' => 'account_action.php',
    '/logout.php' => 'logout.php',
    '/export_csv.php' => 'export_csv.php',
    '/export_pdf.php' => 'export_pdf.php',
];

if (!isset($routes[$requestPath])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="id"><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Halaman tidak ditemukan — JejakKarier</title>';
    echo '<body style="font-family:system-ui;padding:3rem;color:#152238">';
    echo '<h1>404</h1><p>Halaman yang kamu cari tidak tersedia.</p>';
    echo '<a href="/">Kembali ke beranda</a></body></html>';
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/' . $routes[$requestPath];
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
require $projectRoot . DIRECTORY_SEPARATOR . $routes[$requestPath];
