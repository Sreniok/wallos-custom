#!/usr/bin/env php
<?php
declare(strict_types=1);

$appRoot = dirname(__DIR__);
$failures = [];

function smokeAssert(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures[] = $message;
        echo "F";
        return;
    }

    echo ".";
}

function smokeTableExists(SQLite3 $db, string $table): bool
{
    $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
    $stmt->bindValue(':table', $table, SQLITE3_TEXT);
    $result = $stmt->execute();

    return $result->fetchArray(SQLITE3_ASSOC) !== false;
}

function smokeColumns(SQLite3 $db, string $table): array
{
    $columns = [];
    $result = $db->query("PRAGMA table_info($table)");

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }

    return $columns;
}

function smokeRemoveTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        smokeRemoveTree($path . DIRECTORY_SEPARATOR . $entry);
    }

    rmdir($path);
}

function smokeRunPhpLint(string $appRoot): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $output = [];
        $exitCode = 0;
        exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $exitCode);
        smokeAssert($exitCode === 0, 'PHP lint failed for ' . $file->getPathname() . ': ' . implode("\n", $output));
    }
}

function smokeRunFreshDatabaseBootstrap(string $appRoot): void
{
    $tmpDir = sys_get_temp_dir() . '/wallos-smoke-' . getmypid();
    smokeRemoveTree($tmpDir);
    mkdir($tmpDir, 0755, true);

    $dbFile = $tmpDir . '/wallos.db';
    putenv('WALLOS_DATABASE_FILE=' . $dbFile);

    $oldCwd = getcwd();
    chdir($appRoot);

    ob_start();
    require $appRoot . '/endpoints/cronjobs/createdatabase.php';
    require $appRoot . '/endpoints/db/migrate.php';
    ob_end_clean();

    if (isset($db) && $db instanceof SQLite3) {
        $db->close();
        unset($db);
    }

    chdir($oldCwd);
    putenv('WALLOS_DATABASE_FILE');

    $db = new SQLite3($dbFile);

    foreach ([
        'admin',
        'ai_recommendations',
        'ai_settings',
        'email_notifications',
        'login_attempts',
        'migrations',
        'notification_settings',
        'oauth_settings',
        'settings',
        'total_yearly_cost',
    ] as $table) {
        smokeAssert(smokeTableExists($db, $table), "Fresh database is missing table: $table");
    }

    $subscriptionColumns = smokeColumns($db, 'subscriptions');
    foreach ([
        'user_id',
        'url',
        'inactive',
        'notify_days_before',
        'start_date',
        'auto_renew',
        'cancellation_date',
        'replacement_subscription_id',
        'adjust_to_working_day',
        'regular_price',
        'last_payment_date',
        'ended_at',
        'completion_notified',
    ] as $column) {
        smokeAssert(in_array($column, $subscriptionColumns, true), "Fresh subscriptions table is missing column: $column");
    }

    $userColumns = smokeColumns($db, 'user');
    foreach ([
        'language',
        'budget',
        'totp_enabled',
        'api_key',
        'firstname',
        'lastname',
        'oidc_sub',
        'ical_token',
        'ical_enabled',
    ] as $column) {
        smokeAssert(in_array($column, $userColumns, true), "Fresh user table is missing column: $column");
    }

    $notificationSettingColumns = smokeColumns($db, 'notification_settings');
    foreach ([
        'second_notification_enabled',
        'second_notification_days',
        'second_notification_email',
        'second_notification_ntfy',
    ] as $column) {
        smokeAssert(in_array($column, $notificationSettingColumns, true), "Fresh notification_settings table is missing column: $column");
    }

    $db->exec("INSERT INTO subscriptions (name, price, adjust_to_working_day) VALUES ('Migration 48 probe', 1, 0)");
    require $appRoot . '/migrations/000048.php';
    $adjustToWorkingDay = (int) $db->querySingle("SELECT adjust_to_working_day FROM subscriptions WHERE name = 'Migration 48 probe'");
    smokeAssert($adjustToWorkingDay === 1, 'Migration 000048 did not flip old adjust_to_working_day rows to the new semantics');

    $migrationRows = [];
    $result = $db->query('SELECT migration FROM migrations ORDER BY id');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $migrationRows[] = $row['migration'];
    }

    $sortedMigrationRows = $migrationRows;
    sort($sortedMigrationRows, SORT_STRING);
    smokeAssert($migrationRows === $sortedMigrationRows, 'Migrations were not recorded in deterministic sorted order');
    smokeAssert(in_array('migrations/000061.php', $migrationRows, true), 'Latest migration was not applied on a fresh database');

    $db->close();
    smokeRemoveTree($tmpDir);
}

function smokeRunSafeZipTest(string $appRoot): void
{
    if (!class_exists('ZipArchive')) {
        echo "S";
        return;
    }

    require_once $appRoot . '/includes/safe_zip.php';

    $tmpDir = sys_get_temp_dir() . '/wallos-zip-smoke-' . getmypid();
    smokeRemoveTree($tmpDir);
    mkdir($tmpDir, 0755, true);

    $zipPath = $tmpDir . '/unsafe.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFromString('../escape.php', '<?php echo "bad";');
    $zip->close();

    $result = wallosSafeZipExtract($zipPath, $tmpDir . '/extract');
    smokeAssert($result['ok'] === false, 'Safe zip extraction accepted a path traversal entry');
    smokeAssert(!file_exists($tmpDir . '/escape.php'), 'Safe zip extraction wrote outside the destination');

    $limitedZipPath = $tmpDir . '/limited.zip';
    $zip = new ZipArchive();
    $zip->open($limitedZipPath, ZipArchive::CREATE);
    $zip->addFromString('large.txt', str_repeat('x', 1024));
    $zip->close();

    $result = wallosSafeZipExtract($limitedZipPath, $tmpDir . '/limited-extract', [
        'max_files' => 10,
        'max_total_bytes' => 512,
        'max_entry_bytes' => 512,
    ]);
    smokeAssert($result['ok'] === false, 'Safe zip extraction accepted content over the configured size limit');

    smokeRemoveTree($tmpDir);
}

function smokeCreateSqliteDb(string $dbPath, string $marker): void
{
    $db = new SQLite3($dbPath);
    $db->exec('CREATE TABLE user (id INTEGER PRIMARY KEY, username TEXT)');
    $db->exec('CREATE TABLE restore_marker (value TEXT)');
    $stmt = $db->prepare('INSERT INTO restore_marker (value) VALUES (:marker)');
    $stmt->bindValue(':marker', $marker, SQLITE3_TEXT);
    $stmt->execute();
    $db->close();
}

function smokeRunRestoreSafetyTest(string $appRoot): void
{
    if (!class_exists('ZipArchive')) {
        echo "S";
        return;
    }

    require_once $appRoot . '/includes/restore_helpers.php';

    $tmpDir = sys_get_temp_dir() . '/wallos-restore-smoke-' . getmypid();
    smokeRemoveTree($tmpDir);
    mkdir($tmpDir . '/db', 0755, true);
    mkdir($tmpDir . '/.tmp', 0755, true);
    mkdir($tmpDir . '/images/uploads/logos', 0755, true);

    smokeCreateSqliteDb($tmpDir . '/db/wallos.db', 'original');
    smokeCreateSqliteDb($tmpDir . '/restored.db', 'restored');

    $zipPath = $tmpDir . '/restore-input.zip';
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE);
    $zip->addFile($tmpDir . '/restored.db', 'wallos.db');
    $zip->addFromString('logos/restored.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
    $zip->close();

    $result = wallosRestoreBackup([
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($zipPath),
        'tmp_name' => $zipPath,
    ], $tmpDir);
    smokeAssert($result['ok'] === true, 'Restore helper rejected a valid backup zip');

    $db = new SQLite3($tmpDir . '/db/wallos.db');
    $marker = $db->querySingle('SELECT value FROM restore_marker');
    $db->close();
    smokeAssert($marker === 'restored', 'Restore helper did not atomically replace the database');
    smokeAssert(file_exists($tmpDir . '/images/uploads/logos/restored.png'), 'Restore helper did not restore logo files');

    $badZipPath = $tmpDir . '/bad-restore-input.zip';
    $zip = new ZipArchive();
    $zip->open($badZipPath, ZipArchive::CREATE);
    $zip->addFromString('wallos.db', 'not a sqlite database');
    $zip->close();

    $result = wallosRestoreBackup([
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($badZipPath),
        'tmp_name' => $badZipPath,
    ], $tmpDir);
    smokeAssert($result['ok'] === false, 'Restore helper accepted an invalid SQLite database');

    $db = new SQLite3($tmpDir . '/db/wallos.db');
    $marker = $db->querySingle('SELECT value FROM restore_marker');
    $db->close();
    smokeAssert($marker === 'restored', 'Restore helper changed the current database after a failed restore');

    smokeRemoveTree($tmpDir);
}

function smokeRunArchitectureHelperTests(string $appRoot): void
{
    require_once $appRoot . '/includes/request_helpers.php';
    require_once $appRoot . '/includes/subscription_dates.php';

    smokeAssert(
        requestBearerToken(['HTTP_AUTHORIZATION' => 'Bearer test-api-key']) === 'test-api-key',
        'Bearer API token was not parsed from Authorization header'
    );

    $error = null;
    $ids = parseIntegerListParam('1,2, 2,3', 'member', $error);
    smokeAssert($ids === [1, 2, 3] && $error === null, 'Integer list helper did not normalize a valid list');

    parseIntegerListParam('1,nope,3', 'member', $error);
    smokeAssert($error !== null, 'Integer list helper accepted a non-integer list value');

    $progress = getSubscriptionCycleProgress([
        'cycle' => 1,
        'frequency' => 10,
        'next_payment' => '2026-05-11',
    ], new DateTimeImmutable('2026-05-06'));
    smokeAssert($progress === 50, 'Subscription cycle progress helper returned an unexpected value');
}

smokeRunPhpLint($appRoot);
smokeRunFreshDatabaseBootstrap($appRoot);
smokeRunSafeZipTest($appRoot);
smokeRunRestoreSafetyTest($appRoot);
smokeRunArchitectureHelperTests($appRoot);

echo "\n";

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "- $failure\n");
    }
    exit(1);
}

echo "Smoke checks passed.\n";
