<?php
declare(strict_types=1);

final class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private bool $postgres;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->postgres = is_postgres_database();
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $statement = $this->pdo->prepare('SELECT data FROM app_sessions WHERE id = ? LIMIT 1');
        $statement->execute([$id]);
        $encoded = $statement->fetchColumn();
        if (!is_string($encoded) || $encoded === '') {
            return '';
        }

        $decoded = base64_decode($encoded, true);
        return $decoded === false ? '' : $decoded;
    }

    public function write(string $id, string $data): bool
    {
        $encoded = base64_encode($data);
        $timestamp = time();
        $sql = $this->postgres
            ? 'INSERT INTO app_sessions (id, data, last_activity)
               VALUES (?, ?, ?)
               ON CONFLICT (id) DO UPDATE SET
                   data = EXCLUDED.data,
                   last_activity = EXCLUDED.last_activity'
            : 'INSERT INTO app_sessions (id, data, last_activity)
               VALUES (?, ?, ?)
               ON DUPLICATE KEY UPDATE
                   data = VALUES(data),
                   last_activity = VALUES(last_activity)';
        $statement = $this->pdo->prepare($sql);
        return $statement->execute([$id, $encoded, $timestamp]);
    }

    public function destroy(string $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM app_sessions WHERE id = ?');
        return $statement->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = $this->pdo->prepare('DELETE FROM app_sessions WHERE last_activity < ?');
        $statement->execute([time() - $max_lifetime]);
        return $statement->rowCount();
    }
}

function request_uses_https(): bool
{
    $forwardedProtocol = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        $forwardedProtocol === 'https' ||
        environment_value('VERCEL') === '1';
}

function start_application_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $defaultDriver = is_postgres_database() ? 'database' : 'file';
    $driver = strtolower(environment_value('SESSION_DRIVER', $defaultDriver));
    if ($driver === 'database') {
        session_set_save_handler(new DatabaseSessionHandler(database()), true);
    } elseif ($driver !== 'file') {
        throw new RuntimeException('SESSION_DRIVER harus bernilai database atau file.');
    }

    session_name('jejak_karier_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => request_uses_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
