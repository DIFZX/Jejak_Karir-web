<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!extension_loaded('pdo_pgsql')) {
    fwrite(STDERR, "pdo_pgsql belum aktif.\n");
    exit(1);
}

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $source = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        $options
    );
    $targetUrl = environment_value('SUPABASE_DB_URL');
    if ($targetUrl === '') {
        throw new RuntimeException('SUPABASE_DB_URL belum tersedia.');
    }
    $target = connect_to_postgres($targetUrl, $options);
    $target->exec("SET TIME ZONE 'Asia/Jakarta'");
    initialize_postgres_schema($target);

    $sourceUsers = $source->query(
        'SELECT id, username, password, created_at FROM users ORDER BY id'
    )->fetchAll();
    $sourceApplications = $source->query(
        'SELECT id, user_id, company, position, channel, status, priority, notes,
                follow_up_at, interview_at, deadline_at, applied_at, created_at, updated_at
         FROM applications ORDER BY id'
    )->fetchAll();
    $sourceHistory = $source->query(
        'SELECT id, application_id, status, changed_at
         FROM application_status_history ORDER BY id'
    )->fetchAll();

    $sourceUsernames = [];
    foreach ($sourceUsers as $user) {
        $sourceUsernames[$user['username']] = (int) $user['id'];
    }
    foreach ($target->query('SELECT id, username FROM public.users')->fetchAll() as $targetUser) {
        if (isset($sourceUsernames[$targetUser['username']]) &&
            $sourceUsernames[$targetUser['username']] !== (int) $targetUser['id']) {
            throw new RuntimeException(
                'Target memiliki username yang sama dengan ID berbeda. Migrasi dihentikan untuk mencegah data tertimpa.'
            );
        }
    }

    $target->beginTransaction();

    $insertUser = $target->prepare(
        'INSERT INTO public.users (id, username, password, created_at)
         VALUES (:id, :username, :password, :created_at)
         ON CONFLICT (id) DO UPDATE SET
             username = EXCLUDED.username,
             password = EXCLUDED.password,
             created_at = EXCLUDED.created_at'
    );
    foreach ($sourceUsers as $user) {
        $insertUser->execute($user);
    }

    $insertApplication = $target->prepare(
        'INSERT INTO public.applications
         (id, user_id, company, position, channel, status, priority, notes,
          follow_up_at, interview_at, deadline_at, applied_at, created_at, updated_at)
         VALUES
         (:id, :user_id, :company, :position, :channel, :status, :priority, :notes,
          :follow_up_at, :interview_at, :deadline_at, :applied_at, :created_at, :updated_at)
         ON CONFLICT (id) DO UPDATE SET
             user_id = EXCLUDED.user_id,
             company = EXCLUDED.company,
             position = EXCLUDED.position,
             channel = EXCLUDED.channel,
             status = EXCLUDED.status,
             priority = EXCLUDED.priority,
             notes = EXCLUDED.notes,
             follow_up_at = EXCLUDED.follow_up_at,
             interview_at = EXCLUDED.interview_at,
             deadline_at = EXCLUDED.deadline_at,
             applied_at = EXCLUDED.applied_at,
             created_at = EXCLUDED.created_at,
             updated_at = EXCLUDED.updated_at'
    );
    foreach ($sourceApplications as $application) {
        $insertApplication->execute($application);
    }

    $insertHistory = $target->prepare(
        'INSERT INTO public.application_status_history (id, application_id, status, changed_at)
         VALUES (:id, :application_id, :status, :changed_at)
         ON CONFLICT (id) DO UPDATE SET
             application_id = EXCLUDED.application_id,
             status = EXCLUDED.status,
             changed_at = EXCLUDED.changed_at'
    );
    foreach ($sourceHistory as $history) {
        $insertHistory->execute($history);
    }

    $target->exec(
        "SELECT setval(
            pg_get_serial_sequence('public.users', 'id'),
            COALESCE((SELECT MAX(id) FROM public.users), 1),
            EXISTS (SELECT 1 FROM public.users)
        )"
    );
    $target->exec(
        "SELECT setval(
            pg_get_serial_sequence('public.applications', 'id'),
            COALESCE((SELECT MAX(id) FROM public.applications), 1),
            EXISTS (SELECT 1 FROM public.applications)
        )"
    );
    $target->exec(
        "SELECT setval(
            pg_get_serial_sequence('public.application_status_history', 'id'),
            COALESCE((SELECT MAX(id) FROM public.application_status_history), 1),
            EXISTS (SELECT 1 FROM public.application_status_history)
        )"
    );

    $target->commit();

    $targetCounts = [
        'users' => (int) $target->query('SELECT COUNT(*) FROM public.users')->fetchColumn(),
        'applications' => (int) $target->query('SELECT COUNT(*) FROM public.applications')->fetchColumn(),
        'history' => (int) $target->query('SELECT COUNT(*) FROM public.application_status_history')->fetchColumn(),
    ];
    $sourceCounts = [
        'users' => count($sourceUsers),
        'applications' => count($sourceApplications),
        'history' => count($sourceHistory),
    ];

    foreach ($sourceCounts as $table => $count) {
        if ($targetCounts[$table] !== $count) {
            throw new RuntimeException(
                "Verifikasi jumlah $table gagal: sumber=$count, target={$targetCounts[$table]}."
            );
        }
    }

    echo "Migrasi Supabase berhasil.\n";
    echo "users={$targetCounts['users']}\n";
    echo "applications={$targetCounts['applications']}\n";
    echo "history={$targetCounts['history']}\n";
} catch (Throwable $exception) {
    if (isset($target) && $target instanceof PDO && $target->inTransaction()) {
        $target->rollBack();
    }
    fwrite(STDERR, 'Migrasi gagal: ' . $exception->getMessage() . "\n");
    exit(1);
}
