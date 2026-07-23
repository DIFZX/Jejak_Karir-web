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

function environment_flag(string $key, bool $default = false): bool
{
    $value = strtolower(environment_value($key, $default ? 'true' : 'false'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

load_environment_file(dirname(__DIR__) . '/.env');
date_default_timezone_set(environment_value('APP_TIMEZONE', 'Asia/Jakarta'));

define('DB_HOST', environment_value('DB_HOST'));
define('DB_PORT', environment_value('DB_PORT', '3306'));
define('DB_NAME', environment_value('DB_NAME'));
define('DB_USER', environment_value('DB_USER'));
define('DB_PASS', environment_value('DB_PASS'));
define('DB_DRIVER', strtolower(environment_value('DB_DRIVER', 'mysql')));

function is_postgres_database(): bool
{
    return DB_DRIVER === 'pgsql' || DB_DRIVER === 'postgres' || DB_DRIVER === 'postgresql';
}

function connect_to_postgres(string $databaseUrl, array $options): PDO
{
    $parts = parse_url($databaseUrl);
    if ($parts === false || empty($parts['host']) || empty($parts['user']) || !isset($parts['pass'])) {
        throw new RuntimeException('SUPABASE_DB_URL tidak valid. Gunakan URI Session Pooler lengkap dari Supabase.');
    }

    $databaseName = isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres';
    $port = isset($parts['port']) ? (int) $parts['port'] : 5432;
    $dsn = 'pgsql:host=' . $parts['host'] . ';port=' . $port .
        ';dbname=' . ($databaseName ?: 'postgres') . ';sslmode=require';

    return new PDO(
        $dsn,
        rawurldecode($parts['user']),
        rawurldecode($parts['pass']),
        $options
    );
}

function initialize_postgres_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS public.users (
            id BIGSERIAL PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS public.applications (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NULL REFERENCES public.users(id) ON DELETE CASCADE,
            company VARCHAR(150) NOT NULL,
            position VARCHAR(150) NOT NULL,
            channel VARCHAR(100) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'Terkirim'
                CHECK (status IN ('Terkirim','Diproses','HR Screening','Tes','Interview','Offering','Ditolak','Diterima')),
            priority VARCHAR(20) NOT NULL DEFAULT 'Sedang'
                CHECK (priority IN ('Tinggi','Sedang','Rendah')),
            notes TEXT NULL,
            follow_up_at TIMESTAMPTZ NULL,
            interview_at TIMESTAMPTZ NULL,
            deadline_at TIMESTAMPTZ NULL,
            applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS public.application_status_history (
            id BIGSERIAL PRIMARY KEY,
            application_id BIGINT NOT NULL REFERENCES public.applications(id) ON DELETE CASCADE,
            status VARCHAR(50) NOT NULL,
            changed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS public.app_sessions (
            id VARCHAR(128) PRIMARY KEY,
            data TEXT NOT NULL,
            last_activity BIGINT NOT NULL
        )"
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_applications_user_id ON public.applications(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_applications_company ON public.applications(company)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_applications_status ON public.applications(status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_applications_priority ON public.applications(priority)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_applications_applied_at ON public.applications(applied_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_history_application ON public.application_status_history(application_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_history_changed_at ON public.application_status_history(changed_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sessions_last_activity ON public.app_sessions(last_activity)');

    $pdo->exec(
        "CREATE OR REPLACE FUNCTION public.set_application_updated_at()
         RETURNS TRIGGER AS $$
         BEGIN
             NEW.updated_at = CURRENT_TIMESTAMP;
             RETURN NEW;
         END;
         $$ LANGUAGE plpgsql"
    );
    $pdo->exec('DROP TRIGGER IF EXISTS applications_updated_at ON public.applications');
    $pdo->exec(
        'CREATE TRIGGER applications_updated_at
         BEFORE UPDATE ON public.applications
         FOR EACH ROW EXECUTE FUNCTION public.set_application_updated_at()'
    );

    $pdo->exec('ALTER TABLE public.users ENABLE ROW LEVEL SECURITY');
    $pdo->exec('ALTER TABLE public.applications ENABLE ROW LEVEL SECURITY');
    $pdo->exec('ALTER TABLE public.application_status_history ENABLE ROW LEVEL SECURITY');
    $pdo->exec('ALTER TABLE public.app_sessions ENABLE ROW LEVEL SECURITY');

    $initialUsername = environment_value('INITIAL_USERNAME');
    $initialPassword = environment_value('INITIAL_PASSWORD');
    if ($initialUsername !== '' && $initialPassword !== '') {
        $statement = $pdo->prepare('SELECT id FROM public.users WHERE username = ? LIMIT 1');
        $statement->execute([$initialUsername]);
        if (!$statement->fetchColumn()) {
            $insert = $pdo->prepare('INSERT INTO public.users (username, password) VALUES (?, ?)');
            $insert->execute([$initialUsername, password_hash($initialPassword, PASSWORD_DEFAULT)]);
        }
    }
}

function database(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (is_postgres_database()) {
        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('Ekstensi pdo_pgsql belum aktif pada PHP Apache.');
        }
        $databaseUrl = environment_value('SUPABASE_DB_URL');
        if ($databaseUrl === '') {
            throw new RuntimeException('SUPABASE_DB_URL belum tersedia di file .env.');
        }
        try {
            $pdo = connect_to_postgres($databaseUrl, $options);
            $pdo->exec("SET TIME ZONE 'Asia/Jakarta'");
            if (environment_flag('DB_AUTO_MIGRATE')) {
                initialize_postgres_schema($pdo);
            }
            return $pdo;
        } catch (PDOException $exception) {
            error_log('Supabase database error: ' . $exception->getMessage());
            $message = 'Database Supabase tidak dapat dihubungkan.';
            if (environment_flag('APP_DEBUG')) {
                $message .= ' Detail: ' . $exception->getMessage();
            }
            throw new RuntimeException($message);
        }
    }

    if (DB_HOST === '' || DB_NAME === '' || DB_USER === '') {
        throw new RuntimeException(
            'Konfigurasi MySQL belum tersedia. Salin .env.example menjadi .env lalu isi kredensial lokal.'
        );
    }

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
        error_log('MySQL database error: ' . $exception->getMessage());
        $details = environment_flag('APP_DEBUG') ? ' Detail: ' . $exception->getMessage() : '';
        throw new RuntimeException(
            'Database tidak dapat dihubungkan. Pastikan MySQL di XAMPP sudah aktif.' . $details
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
        "CREATE TABLE IF NOT EXISTS app_sessions (
            id VARCHAR(128) PRIMARY KEY,
            data MEDIUMTEXT NOT NULL,
            last_activity BIGINT UNSIGNED NOT NULL,
            INDEX idx_sessions_last_activity (last_activity)
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
