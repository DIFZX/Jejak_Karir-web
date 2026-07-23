<?php
declare(strict_types=1);

function load_environment_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function environment_value(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : (string) $value;
}

load_environment_file(dirname(__DIR__) . '/.env');
date_default_timezone_set(environment_value('APP_TIMEZONE', 'Asia/Jakarta'));

define('DB_HOST', environment_value('DB_HOST'));
define('DB_PORT', environment_value('DB_PORT', '3306'));
define('DB_NAME', environment_value('DB_NAME'));
define('DB_USER', environment_value('DB_USER'));
define('DB_PASS', environment_value('DB_PASS'));

function database(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (DB_HOST === '' || DB_NAME === '' || DB_USER === '') {
        throw new RuntimeException(
            'Konfigurasi database belum tersedia. Salin .env.example menjadi .env lalu isi kredensial lokal.'
        );
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $server = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            $options
        );
        $server->exec(
            'CREATE DATABASE IF NOT EXISTS `' . DB_NAME .
            '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    } catch (PDOException $exception) {
        throw new RuntimeException(
            'Database tidak dapat dihubungkan. Pastikan MySQL di XAMPP sudah aktif. Detail: ' .
            $exception->getMessage()
        );
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        $options
    );
    $pdo->exec("SET time_zone = '+07:00'");

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS applications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company VARCHAR(150) NOT NULL,
            position VARCHAR(150) NOT NULL,
            channel VARCHAR(100) NOT NULL,
            status ENUM('Terkirim','Diproses','Interview','Ditolak','Diterima') NOT NULL DEFAULT 'Terkirim',
            notes TEXT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company (company),
            INDEX idx_status (status),
            INDEX idx_applied_at (applied_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $initialUsername = environment_value('INITIAL_USERNAME');
    $initialPassword = environment_value('INITIAL_PASSWORD');
    $initialUserId = null;

    if ($initialUsername !== '' && $initialPassword !== '') {
        $userStatement = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $userStatement->execute([$initialUsername]);
        $initialUserId = $userStatement->fetchColumn();
        if (!$initialUserId) {
            $insertUser = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
            $insertUser->execute([$initialUsername, password_hash($initialPassword, PASSWORD_DEFAULT)]);
            $initialUserId = $pdo->lastInsertId();
        }
    }
    if (!$initialUserId) {
        $initialUserId = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
    }

    $applicationColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM applications')->fetchAll() as $column) {
        $applicationColumns[$column['Field']] = $column;
    }

    $newColumns = [
        'user_id' => 'ALTER TABLE applications ADD user_id INT UNSIGNED NULL AFTER id',
        'priority' => "ALTER TABLE applications ADD priority ENUM('Tinggi','Sedang','Rendah') NOT NULL DEFAULT 'Sedang' AFTER status",
        'follow_up_at' => 'ALTER TABLE applications ADD follow_up_at DATETIME NULL AFTER notes',
        'interview_at' => 'ALTER TABLE applications ADD interview_at DATETIME NULL AFTER follow_up_at',
        'deadline_at' => 'ALTER TABLE applications ADD deadline_at DATETIME NULL AFTER interview_at',
    ];
    foreach ($newColumns as $columnName => $query) {
        if (!isset($applicationColumns[$columnName])) {
            $pdo->exec($query);
        }
    }

    $statusColumn = $pdo->query("SHOW COLUMNS FROM applications LIKE 'status'")->fetch();
    if ($statusColumn && strpos($statusColumn['Type'], 'Offering') === false) {
        $pdo->exec(
            "ALTER TABLE applications MODIFY status
             ENUM('Terkirim','Diproses','HR Screening','Tes','Interview','Offering','Ditolak','Diterima')
             NOT NULL DEFAULT 'Terkirim'"
        );
    }

    $applicationIndexes = [];
    foreach ($pdo->query('SHOW INDEX FROM applications')->fetchAll() as $index) {
        $applicationIndexes[$index['Key_name']] = true;
    }
    if (!isset($applicationIndexes['idx_user_id'])) {
        $pdo->exec('ALTER TABLE applications ADD INDEX idx_user_id (user_id)');
    }
    if (!isset($applicationIndexes['idx_priority'])) {
        $pdo->exec('ALTER TABLE applications ADD INDEX idx_priority (priority)');
    }

    if ($initialUserId) {
        $assignOwner = $pdo->prepare('UPDATE applications SET user_id = ? WHERE user_id IS NULL');
        $assignOwner->execute([(int) $initialUserId]);
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS application_status_history (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_id INT UNSIGNED NOT NULL,
            status VARCHAR(50) NOT NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_history_application (application_id),
            INDEX idx_history_changed_at (changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "INSERT INTO application_status_history (application_id, status, changed_at)
         SELECT a.id, a.status, a.applied_at
         FROM applications a
         WHERE NOT EXISTS (
             SELECT 1 FROM application_status_history h WHERE h.application_id = a.id
         )"
    );

    return $pdo;
}
